<?php

namespace Light\Controller;

use Exception;
use GraphQL\Error\Error;
use Light\App;
use Light\Model\User;
use Light\Type\WebAuthn;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialUserEntity;


use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialSource;

class WebAuthnController
{
    #[Mutation]
    #[Logged]
    public function deleteWebAuthn(#[InjectUser] User $user, string $uuid): bool
    {
        $data = [];
        foreach ($user->credential as $credential) {
            if ($credential["uuid"] != $uuid) {
                $data[] = $credential;
            }
        }
        $user->credential = $data;
        $user->save();
        return true;
    }

    #[Query]
    #[Logged]
    /**
     * @return \Light\Type\WebAuthn[]
     * @deprecated use my{ webAuthn } instead
     */
    public function listWebAuthn(#[InjectUser] User $user): array
    {
        $data = [];
        foreach ($user->credential as $credential) {
            $data[] = new WebAuthn($credential);
        }
        return $data;
    }


    private function getSerializer()
    {
        $attestationStatementSupportManager = AttestationStatementSupportManager::create();
        $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());
        $factory = new WebauthnSerializerFactory($attestationStatementSupportManager);
        return $factory->create();
    }

    private function getPublicKeyCredentialSourceById(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $serializer = $this->getSerializer();
        foreach (User::Query() as $user) {
            foreach ($user->credential as $credential) {
                if ($credential["credential"]["publicKeyCredentialId"] == $publicKeyCredentialId) {
                    return $serializer->deserialize(json_encode($credential["credential"]), PublicKeyCredentialSource::class, "json");
                }
            }
        }
        return null;
    }

    #[Mutation]
    /**
     * @param mixed $assertion
     */
    public function webAuthnAssertion(?string $username, $assertion, #[Autowire] App $app, #[Autowire] ServerRequestInterface $request): bool
    {
        $serializer = $this->getSerializer();

        $publicKeyCredential  = $serializer->deserialize(json_encode($assertion), PublicKeyCredential::class, 'json');

        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            throw new Error("Invalid response type");
            return false;
        }

        $publicKeyCredentialSource  = $this->getPublicKeyCredentialSourceById($publicKeyCredential->id);

        if (!$publicKeyCredentialSource) {
            throw new Error("Invalid credential1");
            return false;
        }

        //load back the challenge from the cache
        $cache = $app->getCache();
        $request_options = $cache->get("webauthn_request");
        if (!$request_options) {
            throw new Error("Invalid challenge");
            return false;
        }

        $publicKeyCredentialRequestOptions = unserialize($request_options);



        //check
        $csmFactory = new CeremonyStepManagerFactory();
        $creationCSM = $csmFactory->requestCeremony([$app->getRpId()]);
        $authenticatorAssertionResponseValidator = AuthenticatorAssertionResponseValidator::create(ceremonyStepManager: $creationCSM);

        $publicKeyCredentialSource = $authenticatorAssertionResponseValidator->check(
            $publicKeyCredentialSource,
            $publicKeyCredential->response,
            $publicKeyCredentialRequestOptions,
            $request,
            $publicKeyCredentialSource->userHandle
        );

        //remove the challenge from cache
        $cache->delete("webauthn_request");


        $user = User::Get(["user_id" => $publicKeyCredentialSource->userHandle]);
        if (!$user) {
            throw new Error("Invalid user");
            return false;
        }

        //login 
        $app->userLogin($user);

        return true;
    }



    #[Mutation]
    /**
     * @param mixed $registration
     */
    public function webAuthnRegister(#[InjectUser] User $user, #[Autowire] App $app, $registration, #[Autowire] ServerRequestInterface $request): bool
    {

        $csmFactory = new CeremonyStepManagerFactory();
        $creationCSM = $csmFactory->creationCeremony([$app->getRpId()]);
        $authenticatorAttestationResponseValidator = AuthenticatorAttestationResponseValidator::create(ceremonyStepManager: $creationCSM);

        $cache = $app->getCache();
        $creation_option = $cache->get("webauthn_creation_" . $user->user_id);

        if (!$creation_option) {
            throw new \Exception("Invalid credential");
            return false;
        }

        $creation_option = base64_decode($creation_option);


        $attestationStatementSupportManager = AttestationStatementSupportManager::create();
        $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());
        $factory = new WebauthnSerializerFactory($attestationStatementSupportManager);
        $serializer = $factory->create();

        $authenticatorAttestationResponse = $serializer->deserialize(json_encode($registration["response"]), AuthenticatorAttestationResponse::class, 'json');

        $userEntity = new PublicKeyCredentialUserEntity($user->username, $user->user_id, $user->getName());

        $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
            $app->getRpEntity(),
            $userEntity,
            $creation_option,
            [],
        );


        $publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
            $authenticatorAttestationResponse,
            $publicKeyCredentialCreationOptions,
            $request
        );

        if (!$publicKeyCredentialSource) {
            throw new \Exception("Invalid credential");
            return false;
        }


        $user->credential[] = [
            "uuid" => Uuid::uuid4()->toString(),
            "ip" => $_SERVER["REMOTE_ADDR"],
            "user-agent" => $_SERVER["HTTP_USER_AGENT"],
            "timestamp" => time(),
            "credential" => $publicKeyCredentialSource->jsonSerialize()
        ];


        //directly save the credential to the user
        User::_table()->update(
            [
                "credential" => json_encode($user->credential)
            ],
            [
                "user_id" => $user->user_id
            ]
        );


        //remove the challenge from cache
        $cache->delete("webauthn_creation_" . $user->user_id);

        return true;
    }
}
