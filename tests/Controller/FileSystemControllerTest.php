<?php

namespace Light\Tests\Controller;

use Firebase\JWT\JWT;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Model\Config;
use Light\Model\User;
use Light\Model\UserRole;
use Light\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FileSystemControllerTest extends TestCase
{
    protected ?App $app = null;
    protected ?string $adminToken = null;
    protected ?User $adminUser = null;
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "PHPUnit";
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["SCRIPT_FILENAME"] = getcwd() . "/index.php";
        $_SERVER["HTTPS"] = "";

        $this->tmpDir = sys_get_temp_dir() . '/light-fs-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        // Configure a local filesystem pointing at a temp directory.
        // Must be created before App is instantiated because App reads this
        // config in its constructor to build the MountManager.
        Config::Create([
            "name" => "fs",
            "value" => json_encode([
                [
                    "name" => "local",
                    "type" => "local",
                    "data" => [
                        "location" => $this->tmpDir,
                        "public_url" => "/api/uploads/",
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ])->save();

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

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getRealPath());
            } else {
                unlink($entry->getRealPath());
            }
        }
        rmdir($dir);
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

    public function testCreateFolder(): void
    {
        $out = $this->gql(
            'mutation($l:String!){ lightFSCreateFolder(location:$l) }',
            ["l" => "local://test-folder"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["lightFSCreateFolder"]);
        $this->assertDirectoryExists($this->tmpDir . '/test-folder');
    }

    public function testCreateFolderStartingWithDotIsRejected(): void
    {
        $out = $this->gql(
            'mutation($l:String!){ lightFSCreateFolder(location:$l) }',
            ["l" => "local://.hidden"]
        );

        $this->assertArrayHasKey("errors", $out);
        $this->assertStringContainsString("cannot start with a dot", json_encode($out["errors"]));
    }

    public function testWriteAndDeleteFile(): void
    {
        $out = $this->gql(
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://hello.txt", "c" => "world"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["lightFSWriteFile"]);
        $this->assertFileExists($this->tmpDir . '/hello.txt');
        $this->assertEquals("world", file_get_contents($this->tmpDir . '/hello.txt'));

        $out = $this->gql(
            'mutation($l:String!){ lightFSDeleteFile(location:$l) }',
            ["l" => "local://hello.txt"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["lightFSDeleteFile"]);
        $this->assertFileDoesNotExist($this->tmpDir . '/hello.txt');
    }

    public function testRenameFile(): void
    {
        $this->gql(
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://old.txt", "c" => "content"]
        );

        $out = $this->gql(
            'mutation($l:String!,$n:String!){ lightFSRenameFile(location:$l, newName:$n) }',
            ["l" => "local://old.txt", "n" => "new.txt"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["lightFSRenameFile"]);
        $this->assertFileDoesNotExist($this->tmpDir . '/old.txt');
        $this->assertFileExists($this->tmpDir . '/new.txt');
    }

    public function testRenameFileToDisallowedExtensionIsRejected(): void
    {
        $this->gql(
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://safe.txt", "c" => "content"]
        );

        $out = $this->gql(
            'mutation($l:String!,$n:String!){ lightFSRenameFile(location:$l, newName:$n) }',
            ["l" => "local://safe.txt", "n" => "malicious.php"]
        );

        $this->assertArrayHasKey("errors", $out);
        $this->assertStringContainsString("File extension not allowed", json_encode($out["errors"]));
    }

    public function testRenameFolder(): void
    {
        $this->gql(
            'mutation($l:String!){ lightFSCreateFolder(location:$l) }',
            ["l" => "local://folder-a"]
        );

        $out = $this->gql(
            'mutation($l:String!,$n:String!){ lightFSRenameFolder(location:$l, newName:$n) }',
            ["l" => "local://folder-a", "n" => "folder-b"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["lightFSRenameFolder"]);
        $this->assertDirectoryDoesNotExist($this->tmpDir . '/folder-a');
        $this->assertDirectoryExists($this->tmpDir . '/folder-b');
    }

    public function testDeleteFolder(): void
    {
        $this->gql(
            'mutation($l:String!){ lightFSCreateFolder(location:$l) }',
            ["l" => "local://to-delete"]
        );

        $out = $this->gql(
            'mutation($l:String!){ lightFSDeleteFolder(location:$l) }',
            ["l" => "local://to-delete"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["lightFSDeleteFolder"]);
        $this->assertDirectoryDoesNotExist($this->tmpDir . '/to-delete');
    }

    public function testDuplicateFile(): void
    {
        $this->gql(
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://doc.txt", "c" => "original"]
        );

        $out = $this->gql(
            'mutation($l:String!){ lightFSDuplicateFile(location:$l) }',
            ["l" => "local://doc.txt"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertEquals("local://doc (1).txt", $out["data"]["lightFSDuplicateFile"]);
        $this->assertFileExists($this->tmpDir . '/doc.txt');
        $this->assertFileExists($this->tmpDir . '/doc (1).txt');
    }

    public function testMoveNode(): void
    {
        $this->gql(
            'mutation($l:String!){ lightFSCreateFolder(location:$l) }',
            ["l" => "local://src"]
        );
        $this->gql(
            'mutation($l:String!){ lightFSCreateFolder(location:$l) }',
            ["l" => "local://dst"]
        );
        $this->gql(
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://src/movable.txt", "c" => "move me"]
        );

        $out = $this->gql(
            'mutation($f:String!,$t:String!){ lightFSMove(from:$f, to:$t) }',
            ["f" => "local://src/movable.txt", "t" => "local://dst"]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["lightFSMove"]);
        $this->assertFileDoesNotExist($this->tmpDir . '/src/movable.txt');
        $this->assertFileExists($this->tmpDir . '/dst/movable.txt');
    }
}
