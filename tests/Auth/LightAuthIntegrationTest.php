<?php
declare(strict_types=1);

namespace Light\Framework\Tests\Auth;

use GraphQL\Error\Error;
use Laminas\Diactoros\{Response\EmptyResponse, ServerRequest};
use Light\Auth\WebAuthn\{AssertionRequest, RegistrationRequest, Serializer};
use Light\Framework\App;
use Light\Framework\Auth\TokenManager;
use Light\Framework\Controller\{AuthController, WebAuthnController};
use Light\Framework\Model\{Config, User, UserLog};
use Light\Framework\Tests\Fixtures\PasskeyAuthenticator;
use Light\Framework\Tests\TestCase;
use Light\Framework\Type\Auth;
use OTPHP\TOTP;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Webauthn\CredentialRecord;

final class LightAuthIntegrationTest extends TestCase
{
    private App $app;
    private ServerRequestInterface $request;
    private string $binding;
    private array $previousEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousEnv = [$_ENV['RP_ID'] ?? null, $_ENV['WEBAUTHN_ALLOWED_ORIGINS'] ?? null];
        $_ENV['RP_ID'] = 'example.test';
        $_ENV['WEBAUTHN_ALLOWED_ORIGINS'] = 'https://example.test';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = getcwd() . '/index.php';
        $_SERVER['HTTPS'] = 'on';
        $this->binding = bin2hex(random_bytes(32));
        $this->app = new App();
        $this->process();
    }

    protected function tearDown(): void
    {
        foreach (['RP_ID', 'WEBAUTHN_ALLOWED_ORIGINS'] as $i => $key) {
            if ($this->previousEnv[$i] === null) unset($_ENV[$key]);
            else $_ENV[$key] = $this->previousEnv[$i];
        }
        parent::tearDown();
    }

    private function process(?User $user = null, ?string $binding = null): void
    {
        $this->request = (new ServerRequest([], [], 'https://example.test/'))->withCookieParams(['light_auth_binding' => $binding ?? $this->binding]);
        if ($user) {
            $token = TokenManager::encode(['iss' => 'light server', 'type' => 'access_token', 'id' => $user->user_id, 'jti' => bin2hex(random_bytes(16)), 'exp' => time() + 600]);
            $this->request = $this->request->withHeader('Authorization', 'Bearer ' . $token);
        }
        $this->app->process($this->request, new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface { return new EmptyResponse(); }
        });
    }

    private function user(string $username = 'alice'): User
    {
        $user = User::Create([
            'username' => $username, 'first_name' => 'Alice', 'email' => $username . '@example.test',
            'password' => password_hash('correct-password', PASSWORD_DEFAULT), 'join_date' => date('Y-m-d'),
            'status' => 0, 'language' => 'en', 'password_dt' => date('Y-m-d H:i:s'),
        ]);
        $user->save();
        return $user;
    }

    private function config(string $key, string $value): void
    {
        $config = Config::Get(['name' => $key]) ?? Config::Create(['name' => $key]);
        $config->value = $value;
        $config->save();
        Config::Invalidate($key);
    }

    public function testExistingConfigControlsPasswordExpiryAndBothKindsOfLockout(): void
    {
        $this->config('auth_lockout_attempts', '2');
        $this->config('auth_lockout_duration', '7');
        $this->user();
        $controller = new AuthController();
        foreach (['alice', 'missing'] as $username) {
            for ($i = 0; $i < 3; $i++) {
                try { $controller->login($this->app, $username, 'wrong'); self::fail('Login succeeded'); }
                catch (Error $error) { self::assertSame($i === 2 ? 'user is locked for 7 minutes' : 'user not found or password error', $error->getMessage()); }
            }
        }
        $this->config('password_expiration', '1');
        $this->config('password_expiration_duration', '10');
        $old = $this->user('expired');
        $old->password_dt = date('Y-m-d H:i:s', time() - 11 * 86400);
        $old->save();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('password is expired');
        $controller->login($this->app, 'expired', 'correct-password');
    }

    public function testPasswordAndTotpLoginUsesSharedReplayProtection(): void
    {
        $user = $this->user();
        // Existing 16-character secrets remain usable after the column expansion.
        $user->secret = 'JBSWY3DPEHPK3PXP';
        $user->save();
        $code = TOTP::createFromSecret($user->secret)->now();
        $controller = new AuthController();
        self::assertTrue($controller->login($this->app, 'alice', 'correct-password', $code));
        self::assertSame(1, UserLog::Query(['user_id' => $user->user_id, 'result' => 'SUCCESS'])->count());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
        $this->process(binding: bin2hex(random_bytes(32)));
        $this->expectException(Error::class);
        $this->expectExceptionMessage('two factor authentication error');
        $controller->login($this->app, 'alice', 'correct-password', $code);
    }

    public function testConfiguredIpRateLimitAggregatesUsernames(): void
    {
        $this->config('auth_lockout_scope', 'ip');
        if (!enum_exists(\Light\Auth\RateLimitScope::class)) {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Update mathsgod/light-auth');
            $this->app->getAuthentication()->policy();
            return;
        }
        $this->config('auth_lockout_attempts', '2');
        $this->user();
        $controller = new AuthController();
        foreach (['alice', 'missing'] as $name) {
            try { $controller->login($this->app, $name, 'wrong'); self::fail('Invalid login succeeded'); }
            catch (Error $error) { self::assertSame('user not found or password error', $error->getMessage()); }
        }
        try { $controller->login($this->app, 'alice', 'correct-password'); self::fail('IP quota was bypassed'); }
        catch (Error $error) { self::assertStringContainsString('user is locked for', $error->getMessage()); }
        $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
        self::assertTrue($controller->login($this->app, 'alice', 'correct-password'));
    }

    public function testAccountLimitAppliesAcrossIps(): void
    {
        $this->user();
        $controller = new AuthController();
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $_SERVER['REMOTE_ADDR'] = '192.0.2.' . $attempt;
            try {
                $controller->login($this->app, 'alice', 'wrong');
                self::fail('Invalid password was accepted');
            } catch (Error $error) {
                self::assertSame('user not found or password error', $error->getMessage());
            }
        }

        $_SERVER['REMOTE_ADDR'] = '192.0.2.21';
        $this->expectException(Error::class);
        $this->expectExceptionMessage('user is locked for');
        $controller->login($this->app, 'alice', 'correct-password');
    }

    public function testResetPasswordUsesConfiguredPasswordPolicy(): void
    {
        $this->config('password_min_length', '12');
        $this->config('password_expiration', '1');
        $this->config('password_expiration_duration', '10');
        $user = $this->user();
        $expiredAt = date('Y-m-d H:i:s', time() - 11 * 86400);
        $user->password_dt = $expiredAt;
        $user->save();
        $code = '123456';
        $jwt = TokenManager::encode([
            'type' => 'reset_password',
            'user_id' => $user->user_id,
            'code_hash' => hash('sha256', $code . TokenManager::applicationSecret()),
            'exp' => time() + 300,
        ]);
        $controller = new AuthController();

        try {
            $controller->login($this->app, $user->username, 'correct-password');
            self::fail('Expired password was accepted');
        } catch (Error $error) {
            self::assertSame('password is expired', $error->getMessage());
        }

        try {
            $controller->resetPassword($this->app, $jwt, 'too-short', $code);
            self::fail('Password policy was bypassed');
        } catch (Error $error) {
            self::assertSame('Password is not valid to the password policy', $error->getMessage());
        }
        self::assertSame($expiredAt, User::Get($user->user_id)->password_dt);
        self::assertTrue($controller->resetPassword($this->app, $jwt, 'new-password!', $code));
        self::assertTrue($controller->login($this->app, $user->username, 'new-password!'));
    }

    public function testLoggedInUserCanSetUpTotpThroughExistingApiAndSecretCannotBeInvented(): void
    {
        $user = $this->user();
        $this->process($user);
        $setup = $user->getMy2FA($this->app);
        self::assertStringStartsWith('otpauth://totp/', $setup['provisioningUri']);
        parse_str(parse_url($setup['provisioningUri'], PHP_URL_QUERY), $parameters);
        self::assertSame($setup['secret'], $parameters['secret']);
        self::assertArrayNotHasKey('image', $setup);
        self::assertGreaterThan(16, strlen($setup['secret']));
        self::assertEmpty(User::Get($user->user_id)->secret);
        $controller = new AuthController();
        $invented = TOTP::generate();
        try {
            $controller->updateMy2FA($user, $invented->getSecret(), $invented->now(), $this->app);
            self::fail('Client-supplied secret was accepted');
        } catch (Error $error) {
            self::assertSame('Two-factor setup expired or was replaced. Start setup again and scan the new QR code.', $error->getMessage());
        }
        self::assertTrue($controller->updateMy2FA($user, $setup['secret'], TOTP::createFromSecret($setup['secret'])->now(), $this->app));
        self::assertSame($setup['secret'], User::Get($user->user_id)->secret);
    }

    public function testSystemRequiredTotpSetupDialogStillWorksWithoutLoggingIn(): void
    {
        $user = $this->user();
        $this->config('two_factor_authentication', '1');
        $controller = new AuthController();
        try { $controller->login($this->app, 'alice', 'correct-password'); self::fail('User logged in without required TOTP'); }
        catch (Error $error) { self::assertSame('setup_2fa_required', $error->getMessage()); }
        self::assertSame(0, UserLog::Query(['result' => 'SUCCESS'])->count());
        $setup = (new \Light\Framework\Type\App())->generate2FASecret('alice', $this->app);
        self::assertStringStartsWith('otpauth://totp/', $setup['provisioningUri']);
        self::assertTrue($controller->updateTwoFactorAuthentication('alice', 'correct-password', $setup['secret'], TOTP::createFromSecret($setup['secret'])->now(), $this->app));
        self::assertSame($setup['secret'], User::Get($user->user_id)->secret);
        self::assertSame(0, UserLog::Query(['result' => 'SUCCESS'])->count());
    }

    public function testSetupCannotBeStartedByUsernameWithoutPasswordProof(): void
    {
        $this->user();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Password verification is required');
        (new \Light\Framework\Type\App())->generate2FASecret('alice', $this->app);
    }

    public function testGraphqlTotpSetupRetainsTheExistingFrontendShape(): void
    {
        $_ENV['RP_ID'] = 'example.test:8443';
        $user = $this->user('team:alice');
        $this->process($user);
        $request = $this->request->withMethod('POST')->withParsedBody(['query' => 'query { my { my2FA } }']);
        $out = $this->app->execute($request)->toArray();
        self::assertArrayNotHasKey('errors', $out, json_encode($out));
        $setup = $out['data']['my']['my2FA'];
        self::assertArrayHasKey('secret', $setup);
        self::assertStringStartsWith('otpauth://totp/', $setup['provisioningUri']);
        $request = $this->request->withMethod('POST')->withParsedBody([
            'query' => 'mutation($s:String!,$c:String!){ updateMy2FA(secret:$s,code:$c) }',
            'variables' => ['s' => $setup['secret'], 'c' => TOTP::createFromSecret($setup['secret'])->now()],
        ]);
        $out = $this->app->execute($request)->toArray();
        self::assertArrayNotHasKey('errors', $out, json_encode($out));
        self::assertTrue($out['data']['updateMy2FA']);
        self::assertSame($setup['secret'], User::Get($user->user_id)->secret);
    }

    public function testExistingNumericUserHandleCredentialsCanStillLogIn(): void
    {
        $user = $this->user();
        $this->process($user);
        $type = new Auth();
        $controller = new WebAuthnController();
        $authenticator = new PasskeyAuthenticator();
        $creation = $type->getWebAuthnCreationOptions($user, $this->app);
        $controller->webAuthnRegister($user, $this->app, $authenticator->attestation(new RegistrationRequest('', $creation)), $this->request);
        $fresh = User::Get($user->user_id);
        $items = $fresh->credential;
        $record = Serializer::create()->deserialize(json_encode($items[0]['credential']), CredentialRecord::class, 'json');
        $record->userHandle = (string) $user->user_id;
        $items[0]['credential'] = json_decode(Serializer::create()->serialize($record, 'json'), true);
        $fresh->credential = $items;
        $fresh->save();

        $this->process();
        $options = $type->getWebAuthnRequestOptions($this->app, $this->request);
        self::assertTrue($controller->webAuthnAssertion(null, $authenticator->assertion(new AssertionRequest('', $options), $record->userHandle), $this->app, $this->request));
    }

    public function testCreationAndAssertionUseExistingApiAndCredentialJson(): void
    {
        $user = $this->user();
        $this->process($user);
        $type = new Auth();
        $controller = new WebAuthnController();
        $authenticator = new PasskeyAuthenticator();
        $creation = $type->getWebAuthnCreationOptions($user, $this->app);
        self::assertSame('required', $creation['authenticatorSelection']['residentKey']);
        self::assertTrue($controller->webAuthnRegister($user, $this->app, $authenticator->attestation(new RegistrationRequest('', $creation)), $this->request));
        $fresh = User::Get($user->user_id);
        self::assertCount(1, $fresh->credential);
        self::assertSame('127.0.0.1', $fresh->credential[0]['ip']);
        self::assertSame('PHPUnit', $fresh->credential[0]['user-agent']);
        $record = Serializer::create()->deserialize(json_encode($fresh->credential[0]['credential']), CredentialRecord::class, 'json');
        $this->process();
        $options = $type->getWebAuthnRequestOptions($this->app, $this->request);
        $assertion = $authenticator->assertion(new AssertionRequest('', $options), $record->userHandle);
        self::assertTrue($controller->webAuthnAssertion(null, $assertion, $this->app, $this->request));
        $fresh = User::Get($user->user_id);
        self::assertSame(1, $fresh->credential[0]['credential']['counter']);
        self::assertSame(1, UserLog::Query(['result' => 'SUCCESS'])->count());
        self::assertCount(1, $fresh->getWebAuthn());
        $this->expectException(Error::class);
        $controller->webAuthnAssertion(null, $assertion, $this->app, $this->request);
    }

    public function testDifferentBrowserCannotRegisterAndLogoutInvalidatesPendingChallenge(): void
    {
        $user = $this->user();
        $this->process($user);
        $creation = (new Auth())->getWebAuthnCreationOptions($user, $this->app);
        $authenticator = new PasskeyAuthenticator();
        $registration = $authenticator->attestation(new RegistrationRequest('', $creation));
        $this->process($user, str_repeat('c', 64));
        try { (new WebAuthnController())->webAuthnRegister($user, $this->app, $registration, $this->request); self::fail('Different browser registered'); }
        catch (Error $error) { self::assertSame('Invalid challenge', $error->getMessage()); }
        $this->process($user);
        self::assertTrue((new AuthController())->logout($this->app->getAuthService(), $this->app, $this->request));
        // Even resubmitting the old binding after logout cannot use the earlier challenge.
        $this->process($user);
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Invalid challenge');
        (new WebAuthnController())->webAuthnRegister($user, $this->app, $registration, $this->request);
    }
}
