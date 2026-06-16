<?php

namespace Light\Tests\Controller;

use Firebase\JWT\JWT;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Response\EmptyResponse;
use Light\App;
use Light\Model\User;
use Light\Model\UserRole;
use Light\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserControllerTest extends TestCase
{
    protected ?App $app = null;
    protected ?string $adminToken = null;
    protected ?string $currentToken = null;
    protected ?User $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "PHPUnit";
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["SCRIPT_FILENAME"] = getcwd() . "/index.php";
        $_SERVER["HTTPS"] = "";


        // Create admin user before App (so loadRbac sees the UserRole)
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
        $this->currentToken = $this->adminToken;

        // Set up auth service for the admin on the factory
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

    /**
     * Run a GraphQL query/mutation as the current admin (auth service already set in setUp).
     */
    private function gql(string $query, array $variables = []): array
    {
        $token = $this->currentToken ?? $this->adminToken;
        $request = (new ServerRequest())
            ->withMethod("POST")
            ->withHeader("Authorization", "Bearer $token")
            ->withParsedBody(["query" => $query, "variables" => $variables]);

        // Re-process so Auth\Service on factory sees this request's token
        $this->processRequest($this->app, $token);

        return $this->app->execute($request)->toArray(\GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }

    // -----------------------------------------------------------------------
    // addUser
    // -----------------------------------------------------------------------

    private function addUserGql(string $username, string $password = "Test@1234", array $roles = []): array
    {
        $roleList = implode(",", array_map(fn($r) => "\"" . addslashes($r) . "\"", $roles));
        return $this->gql(
            'mutation($u:String!,$p:String!,$f:String!,$e:String!,$r:[String!]){
                addUser(data: { username:$u, password:$p, first_name:$f, email:$e, roles:$r })
            }',
            [
                "u" => $username,
                "p" => $password,
                "f" => "Test",
                "e" => "$username@test.local",
                "r" => $roles,
            ]
        );
    }

    public function testAddUserSuccess(): void
    {
        $username = "new_user_" . uniqid();
        $out = $this->addUserGql($username);

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $uid = $out["data"]["addUser"];
        $this->assertGreaterThan(0, $uid);

        $found = User::Get($uid);
        $this->assertNotNull($found);
        $this->assertEquals($username, $found->username);
    }

    public function testAddUserDuplicateUsername(): void
    {
        $username = "dup_" . uniqid();
        $this->addUserGql($username);

        $out = $this->addUserGql($username);

        $this->assertArrayHasKey("errors", $out);
        $errEntry = $out["errors"][0];
        $msg = $errEntry["extensions"]["debugMessage"] ?? $errEntry["message"];
        $this->assertStringContainsString("already exist", $msg);
    }

    public function testAdminCanAddAnotherAdmin(): void
    {
        $username = "admin2_" . uniqid();
        $out = $this->addUserGql($username, "Test@1234", ["Administrators"]);

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $uid = $out["data"]["addUser"];
        $this->assertGreaterThan(0, $uid);

        $created = User::Get($uid);
        $this->assertNotNull($created);
        $this->assertTrue($created->is("Administrators"));
    }

    public function testNonAdminCannotAddAdministrator(): void
    {
        // Build a non-admin actor (Editor role; no Administrators).
        $editor = User::Create([
            "username" => "editor_" . uniqid(),
            "first_name" => "Ed",
            "email" => "editor_" . uniqid() . "@test.local",
            "password" => password_hash("editor_pw", PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $editor->save();

        UserRole::Create([
            "user_id" => $editor->user_id,
            "role" => "Editor",
        ])->save();

        $editorToken = $this->makeToken($editor->user_id);
        $this->currentToken = $editorToken;
        $this->processRequest($this->app, $editorToken);

        // Editor (no `user.add` right) must be blocked at the GraphQLite
        // RBAC layer (#[Right("user.add")] on UserController::addUser,
        // light/src/Controller/UserController.php:106), regardless of the
        // per-role skip at L143-148.
        $username = "sneaky_" . uniqid();
        $out = $this->addUserGql($username, "Test@1234", ["Administrators"]);

        $this->assertArrayHasKey("errors", $out, json_encode($out));
        $this->assertStringContainsString(
            "do not have sufficient rights",
            json_encode($out["errors"])
        );
        $this->assertSame(
            0,
            User::Query(["username" => $username])->count(),
            "RBAC-blocked addUser must not persist any user"
        );
        $this->assertSame(
            0,
            UserRole::Query(["user_id" => $editor->user_id, "role" => "Administrators"])->count()
        );
    }

    // -----------------------------------------------------------------------
    // updateUser
    // -----------------------------------------------------------------------

    public function testUpdateUser(): void
    {
        $username = "upd_" . uniqid();
        $addOut = $this->addUserGql($username);
        $uid = $addOut["data"]["addUser"];

        $out = $this->gql(
            'mutation($id:Int!,$e:String!){ updateUser(id:$id, data:{ email:$e }) }',
            ["id" => $uid, "e" => "changed@test.local"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["updateUser"]);
        $this->assertEquals("changed@test.local", User::Get($uid)->email);
    }

    // -----------------------------------------------------------------------
    // deleteUser
    // -----------------------------------------------------------------------

    public function testDeleteUser(): void
    {
        $username = "del_" . uniqid();
        $addOut = $this->addUserGql($username);
        $uid = $addOut["data"]["addUser"];

        $out = $this->gql('mutation($id:Int!){ deleteUser(id: $id) }', ["id" => $uid]);

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["deleteUser"]);
        $this->assertNull(User::Get($uid));
    }

    // -----------------------------------------------------------------------
    // changeUserPassword (self-service)
    // -----------------------------------------------------------------------

    public function testChangeUserPasswordSuccess(): void
    {
        $out = $this->gql(
            'mutation($old:String!,$new:String!){ changeUserPassword(old_password:$old, new_password:$new) }',
            ["old" => "admin_pw", "new" => "NewPass@5678"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["changeUserPassword"]);

        $updated = User::Get($this->adminUser->user_id);
        $this->assertTrue(password_verify("NewPass@5678", $updated->password));
    }

    public function testChangeUserPasswordWrongOld(): void
    {
        $out = $this->gql(
            'mutation($old:String!,$new:String!){ changeUserPassword(old_password:$old, new_password:$new) }',
            ["old" => "wrong_old", "new" => "NewPass@5678"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertFalse($out["data"]["changeUserPassword"]);
    }
}
