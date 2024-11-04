<?php

namespace Light\Type;

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


    #[Field]
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

    #[Field]
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
}
