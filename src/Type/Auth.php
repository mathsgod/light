<?php

namespace Light\Type;

use Light\Model\Config;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

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
}
