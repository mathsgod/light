<?php

namespace Light\Controller;

use Firebase\JWT\JWT;
use Laminas\Permissions\Rbac\Rbac;
use Light\App as LightApp;
use Light\Model\Config;
use Light\Model\Role;
use Light\Model\System;
use Light\Model\User;
use Light\Type\App;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class AppController
{
    #[Query]
    public function getApp(): App
    {
        return new App();
    }

    #[Mutation]
    #[Logged]
    function updateAppConfig(string $name, string $value): bool
    {
        if (!$config = Config::Get(["name" => $name])) {
            $config = new Config();
            $config->name = $config;
        }
        $config->value = $value;
        $config->save();
        return true;
    }

    #[Mutation]
    /**
     * @param mixed $data
     */

    function updateAppMenus(array $data): bool
    {
        if (!$menus = Config::Get(["name" => "menus"])) {
            $menus = Config::Create([
                "name" => "menus"
            ]);
        }
        $menus->value = json_encode($data);
        $menus->save();

        return true;
    }

    #[Query()]
    /**
     * @return mixed
     */
    function getAppMenus(#[Autowire] LightApp $app): array
    {
        return $app->getAppMenus();
    }


    #[Mutation]
    #[Logged]
    /**
     * @param mixed $value
     */
    public function updateMyStyle(string $name, #[InjectUser] User $user, $value): bool
    {
        if (is_string($user->style)) {
            $style = json_decode($user->style, true);
            $style[$name] = $value;
            $user->style = json_encode($style, JSON_PRETTY_PRINT);
        } else {
            $user->style[$name] = $value;
        }

        $user->save();
        return true;
    }
}
