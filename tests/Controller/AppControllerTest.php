<?php

namespace Light\Tests\Controller;

use Firebase\JWT\JWT;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Model\Config;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Model\UserRole;
use Light\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AppControllerTest extends TestCase
{
    protected ?App $app = null;
    protected ?string $adminToken = null;
    protected ?User $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "PHPUnit";
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["SCRIPT_FILENAME"] = getcwd() . "/index.php";
        $_SERVER["HTTPS"] = "";

        $this->adminUser = User::Create([
            "username" => "admin_" . uniqid(),
            "first_name" => "Admin",
            "email" => "admin_" . uniqid() . "@test.local",
            "password" => password_hash("admin_pw", PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $this->adminUser->save();

        UserRole::Create([
            "user_id" => $this->adminUser->user_id,
            "role" => "Administrators",
        ])->save();

        $this->app = new App();

        $this->adminToken = $this->makeToken($this->adminUser->user_id);
        $this->processRequest($this->app, $this->adminToken);
    }

    private function makeToken(int $userId, int $ttl = 3600): string
    {
        return JWT::encode([
            "iss"     => "light server",
            "jti"     => "test-" . uniqid(),
            "iat"     => time(),
            "exp"     => time() + $ttl,
            "role"    => "Administrators",
            "id"      => $userId,
            "type"    => "access_token",
            "view_as" => null,
        ], $_ENV["JWT_SECRET"], "HS256");
    }

    private function processRequest(App $app, ?string $token = null): void
    {
        $request = (new ServerRequest())->withMethod("POST");
        if ($token) {
            $request = $request->withHeader("Authorization", "Bearer $token");
        }
        $app->process($request, new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $r): ResponseInterface
            {
                return new EmptyResponse();
            }
        });
    }

    private function gql(string $query, array $variables = []): array
    {
        $request = (new ServerRequest())
            ->withMethod("POST")
            ->withHeader("Authorization", "Bearer " . $this->adminToken)
            ->withParsedBody(["query" => $query, "variables" => $variables]);

        $this->processRequest($this->app, $this->adminToken);

        return $this->app->execute($request)->toArray(\GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }

    public function testUpdateAppConfigsCreatesAndUpdatesConfig(): void
    {
        $key1 = "test_key_" . uniqid();
        $key2 = "test_key_" . uniqid();

        $out = $this->gql(sprintf(
            'mutation { updateAppConfigs(data: [{name: "%s", value: "test_value_1"}, {name: "%s", value: "test_value_2"}]) }',
            $key1,
            $key2
        ));

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateAppConfigs"]);

        $this->assertEquals("test_value_1", Config::Get(["name" => $key1])->value);
        $this->assertEquals("test_value_2", Config::Get(["name" => $key2])->value);

        $out = $this->gql(sprintf(
            'mutation { updateAppConfigs(data: [{name: "%s", value: "updated_value"}]) }',
            $key1
        ));

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertEquals("updated_value", Config::Get(["name" => $key1])->value);
    }

    public function testUpdateAppConfig(): void
    {
        $out = $this->gql(
            'mutation($n:String!,$v:String!){ updateAppConfig(name:$n, value:$v) }',
            ["n" => "single_key", "v" => "single_value"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateAppConfig"]);
        $this->assertEquals("single_value", Config::Get(["name" => "single_key"])->value);
    }

    public function testUpdateAppMenus(): void
    {
        $out = $this->gql(
            'mutation { updateAppMenus(data: [{label: "Home", to: "/", icon: "sym_o_home"}, {label: "User", to: "/User", icon: "sym_o_person"}]) }'
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateAppMenus"]);

        $saved = json_decode(Config::Get(["name" => "menus"])->value, true);
        $this->assertCount(2, $saved);
        $this->assertEquals("Home", $saved[0]["label"]);
    }

    public function testRevokeSession(): void
    {
        $jti = "test-jti-" . uniqid();
        UserLog::Create([
            "user_id" => $this->adminUser->user_id,
            "jti" => $jti,
            "ip" => "127.0.0.1",
            "user_agent" => "PHPUnit",
            "login_dt" => date("Y-m-d H:i:s"),
            "result" => "SUCCESS",
        ])->save();

        $this->assertNull(UserLog::Get(["jti" => $jti, "user_id" => $this->adminUser->user_id])->logout_dt);

        $out = $this->gql(
            'mutation($jti:String!){ revokeSession(jti:$jti) }',
            ["jti" => $jti]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["revokeSession"]);
        $this->assertNotNull(UserLog::Get(["jti" => $jti, "user_id" => $this->adminUser->user_id])->logout_dt);
    }

    public function testUpdateMyStyle(): void
    {
        $out = $this->gql('mutation { updateMyStyle(name: "theme", value: "dark") }');

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateMyStyle"]);

        $updated = User::Get($this->adminUser->user_id);
        $styles = $updated->getStyles();
        $this->assertEquals("dark", $styles["theme"]);
    }

    public function testUpdateMyStyles(): void
    {
        $out = $this->gql('mutation { updateMyStyles(value: {color: "blue", density: "compact"}) }');

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateMyStyles"]);

        $updated = User::Get($this->adminUser->user_id);
        $styles = $updated->getStyles();
        $this->assertEquals("blue", $styles["color"]);
        $this->assertEquals("compact", $styles["density"]);
    }

    public function testUpdateMyLanguage(): void
    {
        $out = $this->gql(
            'mutation($n:String!){ updateMyLanguage(name:$n) }',
            ["n" => "zh-hk"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateMyLanguage"]);
        $this->assertEquals("zh-hk", User::Get($this->adminUser->user_id)->language);
    }

    public function testUpdateMyMenu(): void
    {
        $out = $this->gql(
            'mutation { updateMyMenu(menu: [{label: "Favorites", to: "/User", icon: "sym_o_person"}]) }'
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateMyMenu"]);

        $updated = User::Get($this->adminUser->user_id);
        $this->assertEquals([["label" => "Favorites", "to" => "/User", "icon" => "sym_o_person"]], $updated->getMenu());
    }
}
