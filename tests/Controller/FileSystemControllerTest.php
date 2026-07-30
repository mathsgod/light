<?php

namespace Light\Tests\Controller;

use Firebase\JWT\JWT;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\UploadedFile;
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
        $filesystemConfig = Config::Get(["name" => "fs"]) ?? Config::Create(["name" => "fs"]);
        $filesystemConfig->value = json_encode([
            [
                "name" => "local",
                "type" => "local",
                "data" => [
                    "location" => $this->tmpDir,
                    "public_url" => "/api/uploads/",
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $filesystemConfig->save();

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

    private function makeToken(int $userId, int $ttl = 3600, ?int $viewAs = null): string
    {
        return JWT::encode([
            "iss"     => "light server",
            "jti"     => "test-" . uniqid(),
            "iat"     => time(),
            "exp"     => time() + $ttl,
            "role"    => "Administrators",
            "id"      => $userId,
            "type"    => "access_token",
            "view_as" => $viewAs,
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
        return $this->gqlAs($this->adminToken, $query, $variables);
    }

    private function gqlAs(string $token, string $query, array $variables = []): array
    {
        $request = (new ServerRequest())
            ->withMethod("POST")
            ->withHeader("Authorization", "Bearer " . $token)
            ->withParsedBody(["query" => $query, "variables" => $variables]);

        $this->processRequest($this->app, $token);

        return $this->app->execute($request)->toArray(\GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }

    private function enableAuthenticatedUserScope(): void
    {
        $filesystemConfig = Config::Get(["name" => "fs"]);
        $filesystemConfig->value = json_encode([
            [
                "name" => "local",
                "type" => "local",
                "data" => [
                    "location" => $this->tmpDir,
                    "public_url" => "/api/uploads/",
                ],
                "decorators" => [
                    [
                        "type" => "path_prefix",
                        "data" => [
                            "prefix" => "",
                            "scope" => "authenticated_user",
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $filesystemConfig->save();

        $this->app = new App();
    }

    private function createAdministrator(string $name): array
    {
        $user = User::Create([
            "username" => $name . "_" . uniqid(),
            "first_name" => ucfirst($name),
            "email" => $name . "_" . uniqid() . "@test.local",
            "password" => password_hash("password", PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $user->save();

        UserRole::Create([
            "user_id" => $user->user_id,
            "role" => "Administrators",
        ])->save();

        return [$user, $this->makeToken($user->user_id)];
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

    private function makeUploadedFile(string $filename, string $content): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'light-upload-');
        file_put_contents($tmpPath, $content);
        return new UploadedFile(
            $tmpPath,
            strlen($content),
            UPLOAD_ERR_OK,
            $filename,
            'text/plain'
        );
    }

    public function testUploadFile(): void
    {
        $file = $this->makeUploadedFile('uploaded.txt', 'hello upload');

        $out = $this->gql(
            'mutation($l:String!,$f:Upload!){ lightFSUploadFile(location:$l, file:$f) }',
            ["l" => "local://", "f" => $file]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertEquals("local://uploaded.txt", $out["data"]["lightFSUploadFile"]);
        $this->assertFileExists($this->tmpDir . '/uploaded.txt');
        $this->assertEquals("hello upload", file_get_contents($this->tmpDir . '/uploaded.txt'));
    }

    public function testUploadFileWithRename(): void
    {
        $this->gql(
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://uploaded.txt", "c" => "existing"]
        );

        $file = $this->makeUploadedFile('uploaded.txt', 'second');

        $out = $this->gql(
            'mutation($l:String!,$f:Upload!,$r:Boolean!){ lightFSUploadFile(location:$l, file:$f, rename:$r) }',
            ["l" => "local://", "f" => $file, "r" => true]
        );

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertEquals("local://uploaded (1).txt", $out["data"]["lightFSUploadFile"]);
        $this->assertFileExists($this->tmpDir . '/uploaded.txt');
        $this->assertFileExists($this->tmpDir . '/uploaded (1).txt');
    }

    public function testUploadFileDisallowedExtensionIsRejected(): void
    {
        $file = $this->makeUploadedFile('malicious.php', '<?php echo 1;');

        $out = $this->gql(
            'mutation($l:String!,$f:Upload!){ lightFSUploadFile(location:$l, file:$f) }',
            ["l" => "local://", "f" => $file]
        );

        $this->assertArrayHasKey("errors", $out);
        $this->assertStringContainsString("File type not allowed", json_encode($out["errors"]));
    }

    public function testAuthenticatedUsersAreIsolatedBehindTheSameVirtualPath(): void
    {
        [$secondUser, $secondToken] = $this->createAdministrator('second');
        $this->enableAuthenticatedUserScope();

        $firstWrite = $this->gqlAs(
            $this->adminToken,
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://shared.txt", "c" => "first user"],
        );
        $secondWrite = $this->gqlAs(
            $secondToken,
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://shared.txt", "c" => "second user"],
        );

        $this->assertArrayNotHasKey("errors", $firstWrite, json_encode($firstWrite));
        $this->assertArrayNotHasKey("errors", $secondWrite, json_encode($secondWrite));
        $this->assertSame(
            "first user",
            file_get_contents($this->tmpDir . '/' . $this->adminUser->user_id . '/shared.txt'),
        );
        $this->assertSame(
            "second user",
            file_get_contents($this->tmpDir . '/' . $secondUser->user_id . '/shared.txt'),
        );

        $firstRead = $this->gqlAs(
            $this->adminToken,
            '{ app { fs { node(location:"local://shared.txt") {'
                . ' __typename ... on File { content publicUrl } } } } }',
        );
        $secondRead = $this->gqlAs(
            $secondToken,
            '{ app { fs { node(location:"local://shared.txt") {'
                . ' __typename ... on File { content } } } } }',
        );

        $this->assertSame("first user", $firstRead["data"]["app"]["fs"]["node"]["content"]);
        $this->assertSame("second user", $secondRead["data"]["app"]["fs"]["node"]["content"]);
        $this->assertSame(
            "/api/uploads/" . $this->adminUser->user_id . "/shared.txt",
            $firstRead["data"]["app"]["fs"]["node"]["publicUrl"],
        );

        $this->processRequest($this->app, $this->adminToken);
        $this->assertSame(
            "first user",
            $this->app->getDrive(0)->getFilesystem()->read("shared.txt"),
        );
        $this->processRequest($this->app, $secondToken);
        $this->assertSame(
            "second user",
            $this->app->getDrive(0)->getFilesystem()->read("shared.txt"),
        );

        $legacyFirstWrite = $this->gqlAs(
            $this->adminToken,
            'mutation { fsWriteFile(path:"legacy.txt", content:"legacy first") }',
        );
        $legacySecondWrite = $this->gqlAs(
            $secondToken,
            'mutation { fsWriteFile(path:"legacy.txt", content:"legacy second") }',
        );
        $this->assertArrayNotHasKey("errors", $legacyFirstWrite, json_encode($legacyFirstWrite));
        $this->assertArrayNotHasKey("errors", $legacySecondWrite, json_encode($legacySecondWrite));
        $this->assertSame(
            "legacy first",
            file_get_contents($this->tmpDir . '/' . $this->adminUser->user_id . '/legacy.txt'),
        );
        $this->assertSame(
            "legacy second",
            file_get_contents($this->tmpDir . '/' . $secondUser->user_id . '/legacy.txt'),
        );

        $viewAsToken = $this->makeToken(
            $this->adminUser->user_id,
            viewAs: $secondUser->user_id,
        );
        $viewAsWrite = $this->gqlAs(
            $viewAsToken,
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://view-as.txt", "c" => "viewed user"],
        );
        $this->assertArrayNotHasKey("errors", $viewAsWrite, json_encode($viewAsWrite));
        $this->assertSame(
            "viewed user",
            file_get_contents($this->tmpDir . '/' . $secondUser->user_id . '/view-as.txt'),
        );
    }

    public function testPathTraversalCannotEscapeAuthenticatedUserScope(): void
    {
        [$secondUser, $secondToken] = $this->createAdministrator('second');
        $this->enableAuthenticatedUserScope();

        $this->gqlAs(
            $secondToken,
            'mutation($l:String!,$c:String!){ lightFSWriteFile(location:$l, content:$c) }',
            ["l" => "local://private.txt", "c" => "secret"],
        );

        $out = $this->gqlAs(
            $this->adminToken,
            '{ app { fs { node(location:"local://../'
                . $secondUser->user_id
                . '/private.txt") { __typename } } } }',
        );

        $this->assertArrayHasKey("errors", $out);
        $this->assertStringContainsString(
            "unable to check existence",
            strtolower(json_encode($out["errors"])),
        );
        $this->assertSame(
            "secret",
            file_get_contents($this->tmpDir . '/' . $secondUser->user_id . '/private.txt'),
        );
    }

    public function testFilesystemListDoesNotExposeConfigurationData(): void
    {
        $out = $this->gql('{ app { fs { list } } }');

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertSame([
            [
                "name" => "local",
                "index" => 0,
                "type" => "local",
            ],
        ], $out["data"]["app"]["fs"]["list"]);
    }

    public function testAnonymousRequestCanStartWithUserScopedFilesystemConfigured(): void
    {
        $this->enableAuthenticatedUserScope();

        $this->processRequest($this->app);

        $this->assertNotNull($this->app->getMountManager());
    }
}
