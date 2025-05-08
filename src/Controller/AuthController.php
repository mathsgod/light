<?php

namespace Light\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GraphQL\Error\Error;

use Light\App;
use Light\Auth\Service;
use Light\Input\User as InputUser;
use Light\Model\Config;
use Light\Type\System;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Model\UserRole;
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
    public function changeExpiredPassword(string $username, string $old_password, string $new_password): bool
    {

        $user = User::Get(["username" => $username]);
        if (!$user) {
            throw new Error("User not found");
        }

        //check password is expired ?
        if (!Config::Value("password_expiration")) {
            throw new Error("Password expiration is not enabled");
        }

        //check duration of password
        $duration = Config::Value("password_expiration_duration", 90); //90 days
        $diff = strtotime(date("Y-m-d")) - strtotime($user->password_dt);
        if ($diff < $duration * 24 * 60 * 60) {
            throw new Error("Password is not expired");
        }

        if (!self::PasswordVerify($old_password, $user->password)) {
            throw new Error("Old password is not correct");
        }

        $system = new System();
        if (!$system->isValidPassword($new_password)) {
            throw new Error("Password is not valid to the password policy");
        }


        User::_table()->update([
            "password" => password_hash($new_password, PASSWORD_DEFAULT),
            "password_dt" => date("Y-m-d H:i:s")
        ], [
            "user_id" => $user->user_id
        ]);

        return true;
    }


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


    #[Mutation(name: "lightAuthRegisterFacebook")]
    #[Logged]
    public function registerFacebook(string $access_token, #[InjectUser] User $user): bool
    {
        $client = new \GuzzleHttp\Client([
            "verify" => false
        ]);

        $response = $client->get("https://graph.facebook.com/me?fields=id,name,email", [
            "headers" => [
                "Authorization" => "Bearer " . $access_token
            ]
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        //reset all facebook
        foreach (User::Query(["facebook" => $body["id"]]) as $u) {
            $u->facebook = "";
            $u->save();
        }

        if ($id = $body["id"]) {
            $user->facebook = $id;
            $user->save();
            return true;
        }

        throw new Error("Facebook login error");
    }

    #[Mutation(name: "lightAuthUnlinkFacebook")]
    #[Logged]
    public function unlinkFacebook(#[InjectUser] User $user): bool
    {
        if ($user->facebook) {
            $user->facebook = "";
            $user->save();
        }
        return true;
    }

    #[Mutation(name: "lightAuthUnlinkMicrosoft")]
    #[Logged]
    public function unlinkMicrosoft(#[InjectUser] User $user): bool
    {
        if ($user->microsoft) {
            $user->microsoft = "";
            $user->save();
        }
        return true;
    }

    #[Mutation(name: "lightAuthUnlinkGoogle")]
    #[Logged]
    public function unlinkGoogle(#[InjectUser] User $user): bool
    {
        if ($user->google) {
            $user->google = "";
            $user->save();
        }
        return true;
    }



    //microsoft register
    #[Mutation(name: "lightAuthRegisterMicrosoft")]
    #[Logged]
    function registerMicrosoft(string $account_id, #[InjectUser] User $user): bool
    {
        //reset all microsoft
        foreach (User::Query(["microsoft" => $account_id]) as $u) {
            $u->microsoft = "";
            $u->save();
        }

        $user->microsoft = $account_id;
        $user->save();
        return true;
    }

    //google register
    #[Mutation(name: "lightAuthRegisterGoogle")]
    #[Logged]
    function registerGoogle(string $credential, #[InjectUser] User $user): bool
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            throw new Error("google/apiclient is not installed");
        }

        if (!$google_client_id = Config::Value("authentication_google_client_id")) {
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
            $u->google = "";
            $u->save();
        }

        $user->google = $payload["sub"];
        $user->save();

        return true;
    }

    //microsoft login
    #[Mutation(name: "lightAuthLoginMicrosoft")]
    function loginMicrosoft(string $access_token, #[Autowire] App $app): bool
    {
        if (!Config::Value("authentication_microsoft_client_id")) {
            throw new Error("Microsoft client id is not set");
        }

        $client = new \GuzzleHttp\Client([
            "verify" => false
        ]);

        $response = $client->get("https://graph.microsoft.com/v1.0/me", [
            "headers" => [
                "Authorization" => "Bearer " . $access_token
            ]
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if ($id = $body["id"]) {
            $user = User::Get(["microsoft" => $id, "status" => 0]);
            if (!$user) {
                throw new Error("Microsoft login error");
            }

            $app->userLogin($user);
            return true;
        }

        throw new Error("Microsoft login error");
    }

    //facebook login
    #[Mutation(name: "lightAuthLoginFacebook")]
    public function loginFacebook(string $access_token, #[Autowire] App $app): bool
    {
        if (!Config::Value("authentication_facebook_app_id")) {
            throw new Error("Facebook app id is not set");
        }

        $client = new \GuzzleHttp\Client([
            "verify" => false
        ]);

        $response = $client->get("https://graph.facebook.com/me?fields=id,name,email", [
            "headers" => [
                "Authorization" => "Bearer " . $access_token
            ]
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if ($id = $body["id"]) {
            $user = User::Get(["facebook" => $id, "status" => 0]);
            if (!$user) {
                throw new Error("Facebook login error");
            }

            $app->userLogin($user);
            return true;
        }

        throw new Error("Facebook login error");
    }

    // google login
    #[Mutation(name: "lightAuthLoginGoogle")]
    function loginGoogle(string $credential, #[Autowire] App $app): bool
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            throw new Error("google/apiclient is not installed");
        }

        if (!$google_client_id = Config::Value("authentication_google_client_id")) {
            throw new Error("google client id is not set");
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

        //check no users, create first user
        if (User::Query()->count() == 0) {
            $user = User::Create([
                "username" => $username,
                "first_name" => "Admin",
                "email" => "admin@localhost",
                "password" => password_hash($password, PASSWORD_DEFAULT),
                "join_date" => date("Y-m-d"),
                "status" => 0,
                "language" => "en",
                "password_dt" => date("Y-m-d H:i:s")
            ]);
            //add user to admin group
            UserRole::Create([
                "user_id" => $user->user_id,
                "role" => "Administrators"
            ])->save();
        }



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


        if (Config::Value("password_expiration")) {
            $duration = Config::Value("password_expiration_duration", 90); //90 days
            $diff = strtotime(date("Y-m-d")) - strtotime($user->password_dt);
            if ($diff > $duration * 24 * 60 * 60) {
                throw new Error("password is expired");
            }
        }

        $app->userLogin($user);
        return true;
    }

    private static function PasswordVerify(string $password, string $hash)
    {
        $p = substr($hash, 0, 2);
        if ($p == '$5' || $p == '$6') {
            throw new Error("Password is created with old system, please contact your administrator to reset your password");
        }
        return password_verify($password, $hash);
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
    public function forgetPasswordVerifyCode(#[Autowire] App $app, string $jwt, string $code): bool
    {
        try {
            $payload = JWT::decode($jwt, new Key($_ENV['JWT_SECRET'], 'HS256'));
        } catch (\Exception $e) {
            return false;
        }

        // 限制每個 code 最多 5 次驗證
        $cacheKey = 'reset_code_attempt_' . $payload->code_hash;
        $cache = $app->getCache();

        $attempts = $cache->get($cacheKey) ?? 0;
        if ($attempts >= 5) {
            return false; // 超過次數
        }
        $cache->set($cacheKey, $attempts + 1, 600); // 10分鐘過期

        return ($payload->code_hash == hash('sha256', $code . $_ENV['JWT_SECRET']));
    }

    #[Mutation]
    public function resetPassword(#[Autowire] App $app, string $jwt,  string $password, string $code): bool
    {
        try {
            $payload = JWT::decode($jwt, new Key($_ENV['JWT_SECRET'], 'HS256'));
        } catch (\Exception $e) {
            throw new Error("Code is expired or not valid");
        }

        //verify code
        if ($payload->type != "reset_password") {
            throw new Error("Code is expired or not valid");
        }

        if ($this->forgetPasswordVerifyCode($app, $jwt, $code) == false) {
            throw new Error("Code is expired or not valid");
        }


        $user = User::Get($payload->user_id);
        if (!$user) {
            throw new Error("User not found");
        }

        if ($payload->code_hash != hash('sha256', $code . $_ENV['JWT_SECRET'])) {
            throw new Error("Code is expired or not valid");
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

        return true;
    }

    #[Mutation]
    public function forgetPassword(#[Autowire] App $app, string $username, string $email): string
    {
        if (!$user = User::Get([
            "username" => $username,
            "email" => $email
        ])) {
            return "";
        }

        $code = rand(100000, 999999);

        // send email
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

        // hash code
        $code_hash = hash('sha256', $code . $_ENV['JWT_SECRET']);

        // 產生 JWT
        $payload = [
            'user_id' => $user->user_id,
            'code_hash' => $code_hash,
            'exp' => time() + 600, // 10分鐘
            'iat' => time(), // JWT 發行時間
            'type' => 'reset_password'
        ];
        $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        // 前端收到 jwt，之後 resetPassword/verifyCode 時一齊傳返 server
        return $jwt;
    }
}
