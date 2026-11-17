<?php

namespace Light\Framework\Type;

use Light\Framework\App;
use Light\Framework\Model\Config;
use Light\Framework\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Type;
use Psr\Http\Message\ServerRequestInterface;

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
    /** @return mixed */
    public function getWebAuthnRequestOptions(#[Autowire] App $app, #[Autowire] ServerRequestInterface $request)
    {
        return $app->getAuthentication($request)->beginAssertion();
    }

    #[Field]
    #[Logged]
    /** @return mixed */
    public function getWebAuthnCreationOptions(#[InjectUser] User $user, #[Autowire] App $app)
    {
        return $app->getAuthentication()->beginRegistration($user);
    }
}
