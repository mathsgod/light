<?php

namespace Light\Tests\Auth;

use GraphQL\Error\Error;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Controller\AuthController;
use Light\Model\Config;
use Light\Model\User;
use Light\Security\TwoFactorAuthentication;
use Light\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TwoFactorAuthenticationTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "PHPUnit";
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["SCRIPT_FILENAME"] = getcwd() . "/index.php";
        $_SERVER["HTTPS"] = "";

        $this->app = new App();
        $this->app->process(new ServerRequest(), new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new EmptyResponse();
            }
        });
    }

    protected function tearDown(): void
    {
        Config::Invalidate("two_factor_authentication");
        parent::tearDown();
    }

    private function createUser(string $password = "current_password"): User
    {
        $username = "two_factor_" . uniqid();
        $user = User::Create([
            "username" => $username,
            "first_name" => "Two Factor",
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

    private function enableTwoFactorAuthentication(User $user): string
    {
        $secret = (new TwoFactorAuthentication())->generateSecret();
        $user->secret = $secret;
        $user->save();
        return $secret;
    }

    public function testUserCanDisableTwoFactorAuthentication(): void
    {
        $user = $this->createUser();
        $secret = $this->enableTwoFactorAuthentication($user);
        $code = (new TwoFactorAuthentication())->getCode($secret);

        $result = (new AuthController())->disableMy2FA(
            $user,
            "current_password",
            $code,
            $this->app
        );

        $this->assertTrue($result);
        $this->assertSame("", User::Get($user->user_id)->secret);
    }

    public function testWrongPasswordDoesNotDisableTwoFactorAuthentication(): void
    {
        $user = $this->createUser();
        $secret = $this->enableTwoFactorAuthentication($user);
        $code = (new TwoFactorAuthentication())->getCode($secret);

        $this->expectException(Error::class);
        $this->expectExceptionMessage("Password or two-factor authentication code is incorrect");

        try {
            (new AuthController())->disableMy2FA($user, "wrong_password", $code, $this->app);
        } finally {
            $this->assertSame($secret, User::Get($user->user_id)->secret);
        }
    }

    public function testSystemPolicyPreventsDisablingTwoFactorAuthentication(): void
    {
        $config = Config::Get(["name" => "two_factor_authentication"]);
        if (!$config) {
            $config = Config::Create(["name" => "two_factor_authentication"]);
        }
        $config->value = "1";
        $config->save();
        Config::Invalidate("two_factor_authentication");

        $user = $this->createUser();
        $secret = $this->enableTwoFactorAuthentication($user);
        $code = (new TwoFactorAuthentication())->getCode($secret);

        $this->expectException(Error::class);
        $this->expectExceptionMessage("Two-factor authentication is required by the system");

        try {
            (new AuthController())->disableMy2FA($user, "current_password", $code, $this->app);
        } finally {
            $this->assertSame($secret, User::Get($user->user_id)->secret);
        }
    }

    public function testExistingSecretCannotBeReplacedOrRevealed(): void
    {
        $user = $this->createUser();
        $secret = $this->enableTwoFactorAuthentication($user);
        $replacement = (new TwoFactorAuthentication())->generateSecret();
        $code = (new TwoFactorAuthentication())->getCode($replacement);

        try {
            (new AuthController())->updateMy2FA($user, $replacement, $code, $this->app);
            $this->fail("Expected an enabled account to reject a replacement secret");
        } catch (Error $error) {
            $this->assertSame("Two-factor authentication is already enabled", $error->getMessage());
        }

        $this->assertSame($secret, User::Get($user->user_id)->secret);

        $this->expectException(Error::class);
        $this->expectExceptionMessage("Two-factor authentication is already enabled");
        $user->getMy2FA();
    }
}
