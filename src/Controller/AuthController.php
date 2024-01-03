<?php

namespace Light\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use GraphQL\Error\Error;
use Light\App;
use Light\Auth\Service;
use Light\Input\User as InputUser;
use Light\Model\Config;
use Light\Model\System;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Security\TwoFactorAuthentication;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\UseInputType;

class AuthController
{

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
        foreach (User::Query(["gmail" => $payload["email"]]) as $u) {
            $u->gmail = "";
            $u->save();
        }

        $user->gmail = $payload["email"];
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

        $user = User::Get(["gmail" => $payload["email"], "status" => 0]);
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

        $system = new System();
        if (!$system->isValidPassword($password)) {
            throw new Error("Password is not valid to the password policy");
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
