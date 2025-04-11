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
        //check user database, column facebook is exist
        if (!User::_table()->column("facebook")) {
            User::_table()->addColumn(new \Laminas\Db\Sql\Ddl\Column\Varchar("facebook", 255, true, null, ["comment" => "Facebook ID"]));
        }

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
        //check user database, column facebook is exist
        if (!User::_table()->column("microsoft")) {
            User::_table()->addColumn(new \Laminas\Db\Sql\Ddl\Column\Varchar("microsoft", 255, true, null, ["comment" => "Microsoft ID"]));
        }
        return Config::Value("authentication_microsoft_client_id");
    }

    #[Field]
    function getGoogleClientId(): ?string
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            return null;
        }

        //check user database, column facebook is exist
        if (!User::_table()->column("google")) {
            User::_table()->addColumn(new \Laminas\Db\Sql\Ddl\Column\Varchar("google", 255, true, null, ["comment" => "Google ID"]));
        }

        return Config::Value("authentication_google_client_id");
    }

    #[Field]
    /**
     * @return mixed
     */
    public function getWebAuthnRequestOptions(string $username, #[Autowire] App $app)
    {

        $source = new PublicKeyCredentialSourceRepository();
        if (!$user = User::Get(["username" => $username])) {
            throw new \Exception("Invalid user");
        }

        $userEntity = new PublicKeyCredentialUserEntity($user->username, $user->user_id, $user->getName());

        // Get the list of authenticators associated to the user
        $registeredAuthenticators  = $source->findAllForUserEntity($userEntity);

        // Convert the Credential Sources into Public Key Credential Descriptors
        $allowedCredentials = array_map(
            static function (PublicKeyCredentialSource $credential) {
                return $credential->getPublicKeyCredentialDescriptor();
            },
            $registeredAuthenticators
        );


        $challenge = random_bytes(32);
        $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::create(
            $challenge, // Challenge
            $app->getRpId(), // Relying party ID
            allowCredentials: $allowedCredentials

        );

        $cache = $app->getCache();
        $cache->set("webauthn_request_" . $user->user_id, serialize($publicKeyCredentialRequestOptions), 60 * 5);

        return $publicKeyCredentialRequestOptions->jsonSerialize();
    }

    #[Field]
    #[Logged]
    /**
     * @return mixed
     */
    public function getWebAuthnCreationOptions(#[InjectUser] User $user, #[Autowire] App $app)
    {
        try {
            $rpEntity = $app->getRpEntity();

            $challenge = random_bytes(16);

            //            $server = $app->getWebAuthnServer();

            $userEntity = PublicKeyCredentialUserEntity::create($user->username, $user->user_id, $user->getName());
            $option =
                PublicKeyCredentialCreationOptions::create(
                    $rpEntity,
                    $userEntity,
                    $challenge,
                    []
                );
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }


        /*      // Convert the Credential Sources into Public Key Credential Descriptors
        $option = $server->generatePublicKeyCredentialCreationOptions(
            $userEntity,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE
        );
 */        //save the challenge to cache
        $cache = $app->getCache();
        $cache->set("webauthn_creation_" . $user->user_id, base64_encode($challenge), 60 * 5);

        return $option->jsonSerialize();
    }
}
