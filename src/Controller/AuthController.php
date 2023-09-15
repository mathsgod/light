<?php

namespace Light\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Firebase\JWT\JWT;
use GraphQL\Error\Error;
use Light\App;
use Light\Input\UpdateUser;
use Light\Input\User as InputUser;
use Light\Model\User;
use Light\Security\TwoFactorAuthentication;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Query;

class AuthController
{
    #[Mutation]
    public function logout(): bool
    {
        setcookie("access_token", "", time() - 3600 * 8, "/", "", true, true);
        return true;
    }


    #[Mutation]
    public function login(#[Autowire] App $app, string $username, string $password, ?string $code = null): bool
    {
        $user = User::Get(["username" => $username]);
        if (!$user) {
            throw new Error("user not found or password error");
        }

        if (!self::PasswordVerify($password, $user->password)) {
            throw new Error("user not found or password error");
        }


        //check two factor authentication
        if ($app->isTwoFactorAuthentication()) {
            if (!(new TwoFactorAuthentication)->checkCode($user->secret, $code)) {
                throw new Error("two factor authentication error");
            }
        }

        $payload = [
            "iss" => "light server",
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


    #[Query]
    #[Logged]
    public function getMy(#[InjectUser] User $user): User
    {
        return $user;
    }

    #[Mutation]
    #[Logged]
    public function updateMy(#[InjectUser] User $user, UpdateUser $data): bool
    {
        //filter out all null values
        $d = [];
        foreach ($data as $k => $v) {
            if ($v !== null) {
                $d[$k] = $v;
            }
        }


        $user->bind($d);
        $user->save();
        return true;
    }



    /**
     * Updates the two-factor authentication secret for the authenticated user.
     */
    #[Mutation]
    public function updateMy2FA(#[InjectUser] User $user, string $secret, string $code): bool
    {
        if (!(new TwoFactorAuthentication)->checkCode($secret, $code)) {
            throw new Error("two factor authentication error");
        }

        $user->secret = $secret;
        $user->save();
        return true;
    }


    #[Mutation]
    /**
     * @return mixed
     */
    #[Logged]
    public function getMy2FA(#[InjectUser] User $user)
    {
        $secret = (new TwoFactorAuthentication())->generateSecret();

        $host = $_SERVER["HTTP_HOST"];
        $url = sprintf("otpauth://totp/%s@%s?secret=%s", $user->username, $host, $secret);

        $writer = new PngWriter();
        $png = $writer->write(QrCode::create($url));
        return [
            "secret" => $secret,
            "host" => $host,
            "image" => $png->getDataUri()
        ];
    }

    #[Mutation]
    public function verifyCode(#[Autowire] App $app, string $email, string $code): bool
    {
        $cache = $app->getCache();
        if ($cache->has("forget_password_" . $email)) {
            $cache_code = $cache->get("forget_password_" . $email);
            if ($cache_code == $code) {
                return true;
            }
        }

        return false;
    }

    #[Mutation]
    public function resetPassword(#[Autowire] App $app, string $email, string $password, string $code): bool
    {
        $user = User::Get(["email" => $email]);
        if (!$user) {
            return false;
        }

        $cache = $app->getCache();
        if ($cache->has("forget_password_" . $email)) {
            $cache_code = $cache->get("forget_password_" . $email);
            if ($cache_code == $code) {
                $user->password = password_hash($password, PASSWORD_DEFAULT);
                $user->save();
                return true;
            }
        }

        return false;
    }

    #[Mutation]
    public function forgetPassword(#[Autowire] App $app, string $email): bool
    {

        //check if email exists
        if (!User::Get(["email" => $email])) {
            return "";
        }

        $code = rand(100000, 999999);


        //generate a email to send code to user
        $mailer = $app->getMailer();
        $mailer->addAddress($email);
        $mailer->Subject = "Forget Password";
        $mailer->msgHTML("Your code is " . $code);
        $mailer->send();


        $cache = $app->getCache();
        //5 minutes
        $cache->set("forget_password_" . $email, $code, 300);

        return true;
    }
}
