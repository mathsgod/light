<?php

namespace Light\Type;

use Exception;
use GraphQL\Error\Error;
use Light\App;
use Light\Model\Config;
use Light\Model\User;
use Light\WebAuthn\PublicKeyCredentialSourceRepository;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Type;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

#[Type]
class Auth
{

    #[Field]
    function getFacebookAppId(): ?string
    {
        return  Config::Value("authentication_facebook_app_id");
    }

    #[Field]
    function getMicrosoftTenantId(): ?string
    {
        return Config::Value("authentication_microsoft_tenant_id", "common");
    }

    #[Field]
    function getMicrosoftClientId(): ?string
    {
        return Config::Value("authentication_microsoft_client_id");
    }

    #[Field]
    function getGoogleClientId(): ?string
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            return null;
        }


        return Config::Value("authentication_google_client_id");
    }

    #[Field]
    /**
     * @return mixed
     */
    public function getWebAuthnRequestOptions(#[Autowire] App $app)
    {

        $challenge = random_bytes(32);
        $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::create(
            $challenge, // Challenge
            $app->getRpId(), // Relying party ID
            [],
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        );

        $cache = $app->getCache();
        $cache->set("webauthn_request", serialize($publicKeyCredentialRequestOptions), 60 * 5);

        $json = $publicKeyCredentialRequestOptions->jsonSerialize();

        return $json;
    }

    #[Field]
    #[Logged]
    /**
     * @return mixed
     */
    public function getWebAuthnCreationOptions(#[InjectUser] User $user, #[Autowire] App $app)
    {

        $authenticatorSelectionCriteria = AuthenticatorSelectionCriteria::create(
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
        );


        try {
            $rpEntity = $app->getRpEntity();
            $challenge = random_bytes(16);
            $userEntity = PublicKeyCredentialUserEntity::create($user->username, $user->user_id, $user->getName());
            $option = PublicKeyCredentialCreationOptions::create(
                $rpEntity,
                $userEntity,
                $challenge,
                [],
                $authenticatorSelectionCriteria,
            );
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }

        $cache = $app->getCache();
        $cache->set("webauthn_creation_" . $user->user_id, base64_encode($challenge), 60 * 5);

        return $option->jsonSerialize();
    }
}
