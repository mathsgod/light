<?php

namespace Light\Controller;

use Firebase\JWT\JWT;
use Light\App;
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
    #[Right("mail.send")]
    public function sendMail(#[Autowire] App $app, string $email, string $subject, string $message): bool
    {
        $mailer = $app->getMailer();
        $mailer->addAddress($email);
        $mailer->Subject = $subject;
        $mailer->msgHTML($message);
        $mailer->send();
        return true;
    }

    #[Mutation]
    #[Right("system.mailtest")]
    public function mailTest(#[Autowire] App $app, string $email, string $subject, string $content): bool
    {
        $mailer = $app->getMailer();
        $mailer->addAddress($email);
        $mailer->Subject = $subject;
        $mailer->msgHTML($content);
        $mailer->send();
        return true;
    }

    #[Mutation]
    #[Logged]
    public function cancelViewAs(#[Autowire] \Light\Auth\Service $service, #[Autowire] App $app): bool
    {

        $access_token_expire = $app->getAccessTokenExpire();
        $payload = [
            "iss" => "light server",
            "jti" => Uuid::uuid4()->toString(),
            "iat" => time(),
            "exp" => time() + $access_token_expire,
            "role" =>  $service->getOrginalUser()->getRoles(),
            "id" => $service->getOrginalUser()->user_id,
            "type" => "access_token"
        ];
        $token = JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");
        //set cookie
        setcookie("access_token", $token, time() + $access_token_expire, "/", "", true, true);
        return true;
    }


    #[Mutation]
    #[Logged]
    #[Right("system.view_as")]
    public function viewAs(#[InjectUser] $user, int $user_id, #[Autowire] App $app): bool
    {
        $access_token_expire = $app->getAccessTokenExpire();
        $payload = [
            "iss" => "light server",
            "jti" => Uuid::uuid4()->toString(),
            "iat" => time(),
            "exp" => time() + $access_token_expire,
            "role" => "Users",
            "id" => $user->user_id,
            "view_as" => $user_id,
            "type" => "access_token"
        ];
        $token = JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");
        //set cookie
        setcookie("access_token", $token, time() + $access_token_expire, "/", "", true, true);
        return true;
    }

    #[Query]
    public function system(): System
    {
        return new System;
    }
}
