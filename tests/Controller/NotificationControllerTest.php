<?php

namespace Light\Tests\Controller;

use Firebase\JWT\JWT;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Response\EmptyResponse;
use Light\App;
use Light\Model\Notification;
use Light\Model\User;
use Light\Model\UserRole;
use Light\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NotificationControllerTest extends TestCase
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

    public function testSendNotification(): void
    {
        $out = $this->gql(
            'mutation($u:Int!,$t:String!,$title:String!,$m:String!,$l:String){
                sendNotification(user_id:$u, type:$t, title:$title, message:$m, link:$l)
            }',
            [
                "u" => $this->adminUser->user_id,
                "t" => "info",
                "title" => "Hello",
                "m" => "World",
                "l" => "/User",
            ]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["sendNotification"]);

        $rows = Notification::Query(["user_id" => $this->adminUser->user_id])->toArray();
        $this->assertCount(1, $rows);
        $this->assertEquals("Hello", $rows[0]->title);
        $this->assertEquals(0, $rows[0]->is_read);
    }

    public function testUnreadNotificationCount(): void
    {
        \Light\Model::Notify($this->adminUser->user_id, "warning", "Low disk", "Disk is almost full");

        $out = $this->gql('{ my { unreadNotificationCount } }');

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertEquals(1, $out["data"]["my"]["unreadNotificationCount"]);
    }

    public function testListNotification(): void
    {
        \Light\Model::Notify($this->adminUser->user_id, "info", "A", "Message A");
        \Light\Model::Notify($this->adminUser->user_id, "success", "B", "Message B", "/Role");

        $out = $this->gql('{ app { listNotification { data { notification_id title type link is_read } } } }');

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $data = $out["data"]["app"]["listNotification"]["data"];
        $this->assertCount(2, $data);
        $titles = array_column($data, 'title');
        $this->assertContains("A", $titles);
        $this->assertContains("B", $titles);
    }

    public function testMarkNotificationRead(): void
    {
        $n = \Light\Model::Notify($this->adminUser->user_id, "info", "Read me", "Please");

        $out = $this->gql('mutation($id:Int!){ markNotificationRead(id:$id) }', ["id" => $n->notification_id]);

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["markNotificationRead"]);

        $updated = Notification::Get($n->notification_id);
        $this->assertEquals(1, $updated->is_read);
    }

    public function testMarkAllNotificationsRead(): void
    {
        \Light\Model::Notify($this->adminUser->user_id, "info", "One", "First");
        \Light\Model::Notify($this->adminUser->user_id, "info", "Two", "Second");

        $out = $this->gql('mutation { markAllNotificationsRead }');

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["markAllNotificationsRead"]);

        $unread = Notification::Query(["user_id" => $this->adminUser->user_id, "is_read" => 0])->count();
        $this->assertEquals(0, $unread);
    }

    public function testDeleteNotification(): void
    {
        $n = \Light\Model::Notify($this->adminUser->user_id, "info", "Delete me", "Bye");
        $id = $n->notification_id;

        $out = $this->gql('mutation($id:Int!){ deleteNotification(id:$id) }', ["id" => $id]);

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["deleteNotification"]);
        $this->assertNull(Notification::Get($id));
    }

    public function testUserCannotAccessAnotherUsersNotification(): void
    {
        $other = User::Create([
            "username" => "other_" . uniqid(),
            "first_name" => "Other",
            "email" => "other_" . uniqid() . "@test.local",
            "password" => password_hash("other_pw", PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $other->save();

        $n = \Light\Model::Notify($other->user_id, "info", "Private", "Not yours");

        $out = $this->gql('mutation($id:Int!){ markNotificationRead(id:$id) }', ["id" => $n->notification_id]);

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertFalse($out["data"]["markNotificationRead"]);
        $this->assertEquals(0, Notification::Get($n->notification_id)->is_read);
    }

    public function testDeleteNotificationsBulk(): void
    {
        $n1 = \Light\Model::Notify($this->adminUser->user_id, "info", "One", "First");
        $n2 = \Light\Model::Notify($this->adminUser->user_id, "info", "Two", "Second");

        $out = $this->gql(
            'mutation($ids:[Int!]!){ deleteNotifications(ids:$ids) }',
            ["ids" => [$n1->notification_id, $n2->notification_id]]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["deleteNotifications"]);

        $remaining = Notification::Query(["user_id" => $this->adminUser->user_id])->count();
        $this->assertEquals(0, $remaining);
    }

    public function testUserCannotDeleteAnotherUsersNotificationsBulk(): void
    {
        $other = User::Create([
            "username" => "other_bulk_" . uniqid(),
            "first_name" => "Other",
            "email" => "other_bulk_" . uniqid() . "@test.local",
            "password" => password_hash("other_pw", PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $other->save();

        $n = \Light\Model::Notify($other->user_id, "info", "Private", "Not yours");

        $out = $this->gql(
            'mutation($ids:[Int!]!){ deleteNotifications(ids:$ids) }',
            ["ids" => [$n->notification_id]]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["deleteNotifications"]);
        $this->assertNotNull(Notification::Get($n->notification_id));
    }
}
