<?php

namespace Light\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use GraphQL\Error\Error;
use Light\App;
use Light\Auth\Service;
use Light\Input\User as InputUser;
use Light\Model\Config;
use Light\Type\System;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Security\TwoFactorAuthentication;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\UseInputType;

class AuthController
{

    #[Mutation]
    #[Logged]
    #[Right('user.reset2fa')]
    public function reset2FA(int $id): bool
    {
        $user = User::Get($id);
        if (!$user) {
            throw new Error("User not found");
        }

        $user->secret = "";
        $user->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    public function unlinkGoogle(#[InjectUser] User $user): bool
    {
        if ($user->gmail) {
            $user->gmail = "";
            $user->save();
        }
        return true;
    }

    //google register
    #[Mutation]
    #[Logged]
    function googleRegister(string $credential, #[InjectUser] User $user): bool
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            throw new Error("google/apiclient is not installed");
        }

        if (!$google_client_id = $_ENV["GOOGLE_CLIENT_ID"]) {
            throw new Error("GOOGLE_CLIENT_ID is not set");
        }

        $client = new \Google_Client(["client_id" => $google_client_id]);
        $client->setHttpClient(new \GuzzleHttp\Client(["verify" => false]));
        $payload = $client->verifyIdToken($credential);

        if (!$payload) {
            throw new Error("Google login error");
        }

        // reset all gmail
        foreach (User::Query(["gmail" => $payload["sub"]]) as $u) {
            $u->gmail = "";
            $u->save();
        }

        $user->gmail = $payload["sub"];
        $user->save();

        return true;
    }

    // google login
    #[Mutation] function googleLogin(string $credential, #[Autowire] App $app): bool
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            throw new Error("google/apiclient is not installed");
        }

        if (!$google_client_id = $_ENV["GOOGLE_CLIENT_ID"]) {
            throw new Error("GOOGLE_CLIENT_ID is not set");
        }

        $client = new \Google_Client(["client_id" => $google_client_id]);
        $client->setHttpClient(new \GuzzleHttp\Client(["verify" => false]));
        $payload = $client->verifyIdToken($credential);

        if (!$payload) {
            throw new Error("Google login error");
        }

        $user = User::Get(["gmail" => $payload["sub"], "status" => 0]);
        if (!$user) {
            throw new Error("Google login error");
        }

        $app->userLogin($user);

        return true;
    }


    #[Mutation]
    public function logout(#[Autowire] Service $service, #[Autowire] App $app): bool
    {
        $access_token_expire = $app->getAccessTokenExpire();
        if ($jti = $service->getJti()) {
            $cache = $app->getCache();
            $cache->set("logout_" . $jti, true, $access_token_expire);
        }


        UserLog::_table()->update([
            "logout_dt" => date("Y-m-d H:i:s")
        ], [
            "jti" => $jti
        ]);

        //set cookie
        setcookie("access_token", "", [
            "expires" => time() - 3600,
            "path" => "/",
            "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
            "secure" => $_ENV["COOKIE_SECURE"] ?? false,
            "httponly" => true,
            "samesite" => $_ENV["COOKIE_SAMESITE"] ?? "Lax"
        ]);
        return true;
    }


    #[Mutation]
    public function login(#[Autowire] App $app, string $username, string $password, ?string $code = null): bool
    {
        $user = User::Get(["username" => $username]);
        if (!$user) {
            throw new Error("user not found or password error");
        }

        if ($user->isAuthLocked()) {
            throw new Error("user is locked for " . Config::Value("auth_lockout_duration", 15) . " minutes");
        }

        if (!self::PasswordVerify($password, $user->password)) {
            //save to UserLog
            UserLog::_table()->insert([
                "user_id" => $user->user_id,
                "login_dt" => date("Y-m-d H:i:s"),
                "result" => "FAIL",
                "ip" => $_SERVER["REMOTE_ADDR"],
                "user_agent" => $_SERVER["HTTP_USER_AGENT"],
            ]);

            throw new Error("user not found or password error");
        }

        if ($user->secret) {
            if (!$code) {
                throw new Error("two factor authentication code is required");
            }

            if (!(new TwoFactorAuthentication)->checkCode($user->secret, $code)) {
                throw new Error("two factor authentication error");
            }
        }


        //check two factor authentication
        if ($app->isTwoFactorAuthentication()) {
            if (!(new TwoFactorAuthentication)->checkCode($user->secret, $code)) {
                throw new Error("two factor authentication error");
            }
        }

        $app->userLogin($user);
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
    public function getMy(#[InjectUser] ?User $user): ?User
    {
        return $user;
    }

    #[Mutation]
    #[Logged]
    public function updateMy(#[InjectUser] User $user, #[UseInputType(inputType: "UpdateMyInput")] InputUser $data): bool
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
    #[Logged]
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
    public function verifyCode(#[Autowire] App $app, string $username, string $code): bool
    {
        $user = User::Get(["username" => $username]);
        if (!$user) {
            return false;
        }

        $cache = $app->getCache();
        if ($cache->has("forget_password_" . $user->user_id)) {
            $cache_code = $cache->get("forget_password_" . $user->user_id);
            if ($cache_code == $code) {
                return true;
            }
        }

        return false;
    }

    #[Mutation]
    public function resetPassword(#[Autowire] App $app, string $username, string $password, string $code): bool
    {
        $user = User::Get(["username" => $username]);
        if (!$user) {
            throw new Error("User not found");
        }

        $cache = $app->getCache();
        if (!$cache->has("forget_password_" . $user->user_id)) {
            throw new Error("Code is expired");
        }

        $cache_code = $cache->get("forget_password_" . $user->user_id);
        if ($cache_code != $code) {
            throw new Error("Code is not valid");
        }

        $system = new System();
        if (!$system->isValidPassword($password)) {
            throw new Error("Password is not valid to the password policy");
        }

        User::_table()->update([
            "password" => password_hash($password, PASSWORD_DEFAULT)
        ], [
            "user_id" => $user->user_id
        ]);

        //remove cache
        $cache->delete("forget_password_" . $user->user_id);
        return true;
    }

    #[Mutation]
    public function forgetPassword(#[Autowire] App $app, string $username, string $email): bool
    {

        //check if email exists
        if (!$user = User::Get([
            "username" => $username,
            "email" => $email
        ])) {
            return true;
        }

        $code = rand(100000, 999999);


        //generate a email to send code to user
        $mailer = $app->getMailer();
        $mailer->addAddress($email);

        $mailer->Subject = Config::Value("forget_password_email_subject", "Password Reset Code");

        $template = Config::Value("forget_password_email_template", "Your password reset code is: {code}");
        $mailer->msgHTML(str_replace("{code}", $code, $template));

        try {
            $mailer->send();
        } catch (\Exception $e) {
            throw new Error($e->getMessage());
        }

        $cache = $app->getCache();

        //10 minutes
        $cache->set("forget_password_" . $user->user_id, $code, 600);


        return true;
    }
}
