<?php

namespace Light\Tests\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Auth\Service;
use Light\Model\APIKey;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "PHPUnit";
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["SCRIPT_FILENAME"] = getcwd() . "/index.php";
        $_SERVER["HTTPS"] = "";

        $this->app = new App();
        $this->processRequest(new ServerRequest());
    }

    private function processRequest(ServerRequestInterface $request): void
    {
        $this->app->process($request, new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new EmptyResponse();
            }
        });
    }

    private function createUser(): User
    {
        $username = "session_" . uniqid();
        $user = User::Create([
            "username" => $username,
            "first_name" => "Session",
            "email" => $username . "@test.local",
            "password" => password_hash("session_pw", PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $user->save();
        return $user;
    }

    private function encode(array $payload): string
    {
        return JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");
    }

    private function accessToken(User $user, string $jti, array $extra = []): string
    {
        return $this->encode([
            "iss" => "light server",
            "jti" => $jti,
            "iat" => time(),
            "exp" => time() + 3600,
            "role" => "Users",
            "id" => $user->user_id,
            "view_as" => null,
            "type" => "access_token",
            ...$extra,
        ]);
    }

    private function refreshToken(User $user, string $jti, array $extra = []): string
    {
        return $this->encode([
            "iss" => "light server",
            "jti" => $jti,
            "iat" => time(),
            "exp" => time() + 3600,
            "id" => $user->user_id,
            "type" => "refresh_token",
            ...$extra,
        ]);
    }

    private function createSessionLog(User $user, string $session_id, string $login_dt): UserLog
    {
        $log = UserLog::Create([
            "user_id" => $user->user_id,
            "jti" => $session_id,
            "ip" => "127.0.0.1",
            "user_agent" => "PHPUnit",
            "login_dt" => $login_dt,
            "result" => "SUCCESS",
        ]);
        $log->save();
        return $log;
    }

    public function testLegacyBrowserTokenWithoutSidStillAuthenticates(): void
    {
        $user = $this->createUser();
        $legacy_jti = "legacy-access-" . uniqid();
        $other_jti = "other-session-" . uniqid();
        $one_hour_ago = date("Y-m-d H:i:s", time() - 3600);

        $this->createSessionLog($user, $legacy_jti, $one_hour_ago);
        $this->createSessionLog($user, $other_jti, $one_hour_ago);

        $request = (new ServerRequest())
            ->withHeader("Authorization", "Bearer " . $this->accessToken($user, $legacy_jti));
        $this->processRequest($request);

        $service = $this->app->getAuthService();
        $this->assertTrue($service->isLogged());
        $this->assertSame($legacy_jti, $service->getSessionId());
        $this->assertNotNull(UserLog::Get(["jti" => $legacy_jti])->last_access_time);

        $this->app->getCache()->set("geoip_127.0.0.1", ["status" => "private"], 60);
        $sessions = $user->getSessions($this->app);

        $this->assertCount(2, $sessions);
        $current = array_values(array_filter(
            $sessions,
            fn(array $session): bool => $session["is_current"]
        ));
        $this->assertCount(1, $current);
        $this->assertSame($legacy_jti, $current[0]["jti"]);
    }

    public function testApiKeyWithoutSidStillAuthenticatesWithoutCreatingSession(): void
    {
        $user = $this->createUser();
        $jti = "api-key-" . uniqid();
        $token = $this->accessToken($user, $jti, ["name" => "Integration"]);

        APIKey::Create([
            "name" => "Integration",
            "key" => $token,
            "user_id" => $user->user_id,
            "created_time" => date("Y-m-d H:i:s"),
        ])->save();

        $before = UserLog::Query(["user_id" => $user->user_id])->count();
        $browser_lock_key = "user_sessions_revoked_" . $user->user_id;
        $this->app->getCache()->set($browser_lock_key, true, 60);

        try {
            $request = (new ServerRequest())
                ->withHeader("Authorization", "Bearer " . $token);
            $this->processRequest($request);

            $service = $this->app->getAuthService();
            $this->assertTrue($service->isLogged());
            $this->assertNull($service->getSessionId());
            $this->assertSame($before, UserLog::Query(["user_id" => $user->user_id])->count());
        } finally {
            $this->app->getCache()->delete($browser_lock_key);
        }
    }

    public function testStableSessionIdSurvivesRefresh(): void
    {
        $user = $this->createUser();
        $session_id = "session-" . uniqid();
        $refresh_jti = "refresh-" . uniqid();
        $this->createSessionLog($user, $session_id, date("Y-m-d H:i:s", time() - 1800));

        $request = (new ServerRequest(["REMOTE_ADDR" => "127.0.0.1"]))
            ->withCookieParams([
                "refresh_token" => $this->refreshToken($user, $refresh_jti, ["sid" => $session_id]),
            ])
            ->withHeader("User-Agent", "PHPUnit");

        $response = $this->app->handleRefreshToken($request);

        $this->assertSame(200, $response->getStatusCode());
        $grace = $this->app->getCache()->get("refresh_token_grace_" . $refresh_jti);
        $this->assertIsArray($grace);

        $key = new Key($_ENV["JWT_SECRET"], "HS256");
        $access_payload = JWT::decode($grace["access_token"], $key);
        $refresh_payload = JWT::decode($grace["refresh_token"], $key);

        $this->assertSame($session_id, $access_payload->sid);
        $this->assertSame($session_id, $refresh_payload->sid);
        $this->assertNotSame($session_id, $access_payload->jti);
        $this->assertNotSame($refresh_jti, $refresh_payload->jti);
        $this->assertNotNull(UserLog::Get(["jti" => $session_id])->last_access_time);
    }

    public function testLegacyRefreshTokenMigratesWithoutLoggingOut(): void
    {
        $user = $this->createUser();
        $refresh_jti = "legacy-refresh-" . uniqid();
        $request = (new ServerRequest(["REMOTE_ADDR" => "127.0.0.1"]))
            ->withCookieParams([
                "refresh_token" => $this->refreshToken($user, $refresh_jti),
            ])
            ->withHeader("User-Agent", "Legacy API client");

        $response = $this->app->handleRefreshToken($request);

        $this->assertSame(200, $response->getStatusCode());
        $grace = $this->app->getCache()->get("refresh_token_grace_" . $refresh_jti);
        $key = new Key($_ENV["JWT_SECRET"], "HS256");
        $access_payload = JWT::decode($grace["access_token"], $key);

        $log = UserLog::Get([
            "user_id" => $user->user_id,
            "jti" => $access_payload->sid,
        ]);
        $this->assertNotNull($log);
        $this->assertSame("Legacy API client", $log->user_agent);
    }

    public function testRevokedBrowserSessionCannotAuthenticateOrRefresh(): void
    {
        $user = $this->createUser();
        $session_id = "revoked-session-" . uniqid();
        $access_jti = "revoked-access-" . uniqid();
        $refresh_jti = "revoked-refresh-" . uniqid();
        $this->createSessionLog($user, $session_id, date("Y-m-d H:i:s"));

        $this->app->getCache()->set(
            Service::REVOKED_SESSION_PREFIX . $session_id,
            true,
            $this->app->getRefreshTokenExpire()
        );

        $access_request = (new ServerRequest())
            ->withHeader(
                "Authorization",
                "Bearer " . $this->accessToken($user, $access_jti, ["sid" => $session_id])
            );
        $this->processRequest($access_request);
        $this->assertFalse($this->app->getAuthService()->isLogged());

        $refresh_request = (new ServerRequest())->withCookieParams([
            "refresh_token" => $this->refreshToken($user, $refresh_jti, ["sid" => $session_id]),
        ]);
        $response = $this->app->handleRefreshToken($refresh_request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame("Session revoked", (string) $response->getBody());
    }
}
