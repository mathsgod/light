<?php

namespace Light\Tests\Auth;

use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Tests\TestCase;

class AppLoginTest extends TestCase
{
    protected ?App $app = null;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "PHPUnit";
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["SCRIPT_FILENAME"] = getcwd() . "/index.php";
        $_SERVER["HTTPS"] = "";

        // light-server scans <root>/pages — ensure it exists in CI
        if (!is_dir(getcwd() . "/pages")) {
            mkdir(getcwd() . "/pages", 0777, true);
        }
    }

    private function getApp(): App
    {
        if ($this->app === null) {
            $this->app = new App();
            // register a default Auth\Service so Model::save() works
            $request = new ServerRequest();
            $this->app->process($request, new class implements \Psr\Http\Server\RequestHandlerInterface {
                public function handle(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface
                {
                    return new \Laminas\Diactoros\Response\EmptyResponse();
                }
            });
        }
        return $this->app;
    }

    private function createUser(string $username, string $password): User
    {
        $this->getApp();
        $user = User::Create([
            "username" => $username,
            "first_name" => "Test",
            "email" => $username . "@test.local",
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $user->save();
        return $user;
    }

    private function gql(App $app, string $query, array $variables = [])
    {
        $request = (new ServerRequest())
            ->withMethod("POST")
            ->withParsedBody(["query" => $query, "variables" => $variables]);
        $result = $app->execute($request);
        return $result->toArray();
    }

    public function testLoginSuccess(): void
    {
        $username = "applogin_ok_" . uniqid();
        $password = "pw12345";
        $user = $this->createUser($username, $password);

        $app = $this->getApp();
        $out = $this->gql($app, 'mutation($u:String!,$p:String!){ login(username:$u, password:$p) }', [
            "u" => $username,
            "p" => $password,
        ]);

        $this->assertArrayNotHasKey("errors", $out, json_encode($out));
        $this->assertTrue($out["data"]["login"]);

        $logs = iterator_to_array(UserLog::Query(["user_id" => $user->user_id]));
        $this->assertCount(1, $logs);
        $this->assertEquals("SUCCESS", $logs[0]->result);
    }

    public function testLoginWrongPasswordCreatesFailLog(): void
    {
        $username = "applogin_bad_" . uniqid();
        $user = $this->createUser($username, "correct_pw");

        $app = $this->getApp();
        $out = $this->gql($app, 'mutation($u:String!,$p:String!){ login(username:$u, password:$p) }', [
            "u" => $username,
            "p" => "wrong_pw",
        ]);

        $this->assertArrayHasKey("errors", $out);
        $this->assertStringContainsString("password error", $out["errors"][0]["message"]);

        $logs = iterator_to_array(UserLog::Query(["user_id" => $user->user_id]));
        $this->assertCount(1, $logs);
        $this->assertEquals("FAIL", $logs[0]->result);
    }

    public function testLoginUnknownUser(): void
    {
        $app = $this->getApp();
        $out = $this->gql($app, 'mutation { login(username:"no_such_user_xyz_999", password:"x") }');

        $this->assertArrayHasKey("errors", $out);
        $this->assertStringContainsString("not found", $out["errors"][0]["message"]);
    }
}
