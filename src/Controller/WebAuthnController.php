<?php

namespace Light\Controller;

use GraphQL\Error\Error;
use Light\App;
use Light\Model\User;
use Light\WebAuthn\PublicKeyCredentialSourceRepository;
use Light\Type\WebAuthn;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

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
     */
    public function listWebAuthn(#[InjectUser] User $user): array
    {
        $data = [];
        foreach ($user->credential as $credential) {
            $data[] = new WebAuthn($credential);
        }
        return $data;
    }

    public function getWebAuthnServer()
    {
        $name = $_SERVER["SERVER_NAME"];
        if ($name == "0.0.0.0") {
            $name = "localhost";
            $id = "localhost";
        } else {
            $name = $_SERVER["SERVER_NAME"];
            if (!$_ENV["RP_ID"]) {
                throw new Error("RP_ID is not set in .env file");
            }
        }


        $rp = new PublicKeyCredentialRpEntity($name, $id);
        $source = new PublicKeyCredentialSourceRepository();
        $server = new \Webauthn\Server($rp, $source);
        $server->setSecuredRelyingPartyId(["localhost"]);
        return $server;
    }


    #[Mutation]
    /**
     * @param mixed $assertion
     */
    public function webAuthnAssertion(string $username, $assertion, #[Autowire] App $app, #[Autowire] ServerRequestInterface $request): bool
    {
        $server = $this->getWebAuthnServer();
        if (!$user = User::Get(["username" => $username])) {
            throw new \Exception("Invalid user");
        }

        $userEntity = new PublicKeyCredentialUserEntity($user->username, $user->user_id, $user->getName());

        $cache = $app->getCache();
        $request_options = $cache->get("webauthn_request_" . $user->user_id);

        if (!$request_options) {
            throw new \Exception("Invalid credential");
            return false;
        }

        $request_options = json_decode($request_options, true);

        $server->loadAndCheckAssertionResponse(
            json_encode($assertion),
            PublicKeyCredentialRequestOptions::createFromArray($request_options),
            $userEntity,
            $request
        );

        //remove the challenge from cache
        $cache->delete("webauthn_request_" . $user->user_id);

        //login 
        $app->userLogin($user);

        return true;
    }


    #[Query]
    /**
     * @return mixed
     */
    public function getWebAuthnRequestOptions(string $username, #[Autowire] App $app)
    {
        $server = $this->getWebAuthnServer();
        $source = new PublicKeyCredentialSourceRepository();
        if (!$user = User::Get(["username" => $username])) {
            throw new \Exception("Invalid user");
        }

        $userEntity = new PublicKeyCredentialUserEntity($user->username, $user->user_id, $user->getName());

        // Get the list of authenticators associated to the user
        $credentialSources = $source->findAllForUserEntity($userEntity);

        // Convert the Credential Sources into Public Key Credential Descriptors
        $allowedCredentials = array_map(function (PublicKeyCredentialSource $credential) {
            return $credential->getPublicKeyCredentialDescriptor();
        }, $credentialSources);

        // We generate the set of options.
        $option = $server->generatePublicKeyCredentialRequestOptions(
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED, // Default value
            $allowedCredentials
        );

        $cache = $app->getCache();
        $cache->set("webauthn_request_" . $user->user_id, json_encode($option), 60 * 5);

        return $option->jsonSerialize();
    }

    #[Query]
    #[Logged]
    /**
     * @return mixed
     */
    public function getWebAuthnCreationOptions(#[InjectUser] User $user, #[Autowire] App $app)
    {

        $server = $this->getWebAuthnServer();
        $userEntity = new PublicKeyCredentialUserEntity($user->username, $user->user_id, $user->getName());

        // Convert the Credential Sources into Public Key Credential Descriptors
        $option = $server->generatePublicKeyCredentialCreationOptions(
            $userEntity,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE
        );

        //save the challenge to cache
        $cache = $app->getCache();
        $cache->set("webauthn_creation_" . $user->user_id, json_encode($option), 60 * 5);

        return $option->jsonSerialize();
    }

    #[Mutation]
    /**
     * @param mixed $registration
     */
    public function webAuthnRegister(#[InjectUser] User $user, #[Autowire] App $app, $registration, #[Autowire] ServerRequestInterface $request): bool
    {
        $cache = $app->getCache();
        $creation_option = $cache->get("webauthn_creation_" . $user->user_id);

        if (!$creation_option) {
            throw new \Exception("Invalid credential");
            return false;
        }

        $creation_option = json_decode($creation_option, true);

        $server = $this->getWebAuthnServer();

        $uri = $request->getUri()->withScheme("https");
        $request = $request->withUri($uri);

        $server->setSecuredRelyingPartyId(["localhost"]);

        $credentialSource = $server->loadAndCheckAttestationResponse(
            json_encode($registration),
            PublicKeyCredentialCreationOptions::createFromArray($creation_option),
            $request
        );

        if (!$credentialSource) {
            throw new \Exception("Invalid credential");
            return false;
        }

        $user->credential[] = [
            "uuid" => Uuid::uuid4()->toString(),
            "ip" => $_SERVER["REMOTE_ADDR"],
            "user-agent" => $_SERVER["HTTP_USER_AGENT"],
            "timestamp" => time(),
            "credential" => $credentialSource->jsonSerialize()
        ];

        $user->save();

        //remove the challenge from cache
        $cache->delete("webauthn_creation_" . $user->user_id);

        return true;
    }
}
