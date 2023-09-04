<?php

namespace Light\Controller;

use Firebase\JWT\JWT;
use Laminas\Permissions\Rbac\Rbac;
use Light\Model\Role;
use Light\Model\System;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class SystemController
{

    #[Mutation]
    #[Logged]
    public function cancelViewAs(#[InjectUser] $user,#[Autowire] ): bool
    {
        $payload = [
            "iss" => "light server",
            "jti" => Uuid::uuid4()->toString(),
            "iat" => time(),
            "exp" => time() + 3600 * 8,
            "role" => "Users",
            "id" => $user->user_id,
            "type" => "access_token"
        ];
        $token = JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");
        //set cookie
        setcookie("access_token", $token, time() + 3600 * 8, "/", "", true, true);
        return true;
    }


    #[Mutation]
    #[Logged]
    public function viewAs(#[InjectUser] $user, int $user_id): bool
    {
        $payload = [
            "iss" => "light server",
            "jti" => Uuid::uuid4()->toString(),
            "iat" => time(),
            "exp" => time() + 3600 * 8,
            "role" => "Users",
            "id" => $user->user_id,
            "view_as" => $user_id,
            "type" => "access_token"
        ];
        $token = JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");
        //set cookie
        setcookie("access_token", $token, time() + 3600 * 8, "/", "", true, true);
        return true;
    }

    #[Query]
    public function system(): System
    {
        return new System;
    }
}
