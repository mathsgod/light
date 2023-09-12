<?php

namespace Light\Controller;

use Firebase\JWT\JWT;
use Laminas\Permissions\Rbac\Rbac;
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
    function updateAppConfig(#[InjectUser()] User $user, string $name, string $value): bool
    {
        if (!$config = Config::Get(["name" => $name])) {
            $config = new Config();
            $config->name = $config;
        }
        $config->value = $value;
        $config->save();
        return true;
    }
}
