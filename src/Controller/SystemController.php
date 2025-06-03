<?php

namespace Light\Controller;

use Exception;
use Firebase\JWT\JWT;
use GraphQL\Error\Error;
use Light\App;
use Light\Type\System;
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
        try {
            $mailer->send();
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }


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
        // setcookie("access_token", $token, time() + $access_token_expire, "/", "", true, true);
        $samesite = $_ENV["COOKIE_SAMESITE"] ?? "Lax";
        if ($_SERVER["HTTPS"] == "on" && $_ENV["COOKIE_PARTITIONED"] == "true") {
            $samesite .= ";Partitioned";
        }
        //set cookie
        setcookie("access_token", $token, [
            "expires" => time() + $access_token_expire,
            "path" => "/",
            "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
            "secure" => $_ENV["COOKIE_SECURE"] ?? false,
            "httponly" => true,
            "samesite" => $samesite
        ]);
        return true;
    }

    #[Query]
    public function system(): System
    {
        return new System;
    }

    #[Mutation]
    #[Right("system.database.field.add")]
    public function addDatabaseField(#[Autowire] App $app, string $table, string $field, string $type, string $length, string $default, bool $nullable, bool $autoincrement): string
    {
        $db = $app->getDatabase();
        $db->exec("ALTER TABLE $table ADD $field $type($length) DEFAULT '$default' " . ($nullable ? "NULL" : "NOT NULL") . " " . ($autoincrement ? "AUTO_INCREMENT" : ""));
        return "Field $field added to table $table";
    }

    #[Mutation]
    #[Right("system.database.field.remove")]
    /**
     * @param string[] $fields
     */
    public function removeDatabaseFields(#[Autowire] App $app, string $table, array $fields): string
    {
        $db = $app->getDatabase();
        foreach ($fields as $field) {
            $db->exec("ALTER TABLE $table DROP COLUMN $field");
        }
        return "Fields " . implode(", ", $fields) . " removed from table $table";
    }
}
