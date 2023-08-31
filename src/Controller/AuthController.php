<?php

namespace Light\Controller;

use Error;
use Firebase\JWT\JWT;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Mutation;

class AuthController
{
    #[Mutation]
    public function logout(): bool
    {
        setcookie("access_token", "", time() - 3600 * 8, "/", "", true, true);
        return true;
    }


    #[Mutation]
    public function login(string $username, string $password): string
    {
        $user = User::Get(["username" => $username]);
        if (self::PasswordVerify($password, $user->password)) {
            $payload = [
                "iss" => "https://api.e-post.com.hk/v5",
                "iat" => time(),
                "exp" => time() + 3600 * 8,
                "role" => "Users",
                "id" => $user->user_id,
                "type" => "access_token"
            ];

            $token = JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");

            //set cookie
            setcookie("access_token", $token, time() + 3600 * 8, "/", "", true, true);
            return $token;
        }
        throw new Error("password error");
    }

    private static function PasswordVerify(string $password, string $hash)
    {
        $p = substr($hash, 0, 2);
        if ($p == '$5' || $p == '$6') {
            $pass = "";
            $md5 = md5($password);
            eval(base64_decode("JHBhc3MgPSBtZDUoc3Vic3RyKHN1YnN0cigkbWQ1LC0xNiksLTgpLnN1YnN0cihzdWJzdHIoJG1kNSwtMTYpLDAsLTgpLnN1YnN0cihzdWJzdHIoJG1kNSwwLC0xNiksLTgpLnN1YnN0cihzdWJzdHIoJG1kNSwwLC0xNiksMCwtOCkpOw=="));
            return crypt($pass, $hash) == $hash;
        } else {
            return password_verify($password, $hash);
        }
    }
}
