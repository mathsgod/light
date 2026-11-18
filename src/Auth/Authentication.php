<?php
declare(strict_types=1);

namespace Light\Framework\Auth;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use GraphQL\Error\Error;
use Light\Auth\{AttemptLimiter, Challenges, LoginService, Policy, RateLimitScope, Status, TotpEnrollment, TotpVerifier};
use Light\Auth\Contract\StateStore;
use Light\Auth\Pdo\PdoCredentialRepository;
use Light\Auth\WebAuthn\{WebAuthnEnrollment, WebAuthnService};
use Light\Framework\App;
use Light\Framework\Database\Sql;
use Light\Framework\Model\{Config, User};
use Ramsey\Uuid\Uuid;

/** Application adapter: API compatibility, browser binding, audit and persistence around light-auth. */
final class Authentication
{
    private const TTL = 300;
    private ?object $bindingRequest = null;
    private ?string $binding = null;

    public function __construct(private App $app, private StateStore $state) {}

    public static function defaultStateStore(\Psr\SimpleCache\CacheInterface $cache): StateStore
    {
        return new \Light\Auth\Cache\CacheStateStore(
            $cache,
            new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\FlockStore()),
            'light_auth_' . hash('sha256', getcwd() . ':' . ($_ENV['DATABASE_DATABASE'] ?? '')),
        );
    }

    public function policy(): Policy
    {
        $attempts = max(1, (int) Config::Value('auth_lockout_attempts', '5'));
        $seconds = max(1, (int) Config::Value('auth_lockout_duration', '15')) * 60;
        $scope = Config::Value('auth_lockout_scope', 'username_ip') ?? 'username_ip';
        // Older pinned installations retain their default behavior until light-auth is updated.
        if (!enum_exists(RateLimitScope::class)) {
            if ($scope !== 'username_ip') throw new \LogicException('Update mathsgod/light-auth before selecting an IP rate limit.');
            return new Policy($attempts, $seconds, self::TTL);
        }
        $selected = RateLimitScope::tryFrom($scope);
        if ($selected === null) throw new \InvalidArgumentException('auth_lockout_scope must be username_ip or ip.');
        return new Policy(
            maxAttempts: $attempts,
            windowSeconds: $seconds,
            challengeSeconds: self::TTL,
            scope: $selected,
        );
    }

    private function limiter(): AttemptLimiter { return new AttemptLimiter($this->state, $this->policy()); }
    private function challenges(): Challenges { return new Challenges($this->state, $this->policy()); }
    private function totp(): TotpVerifier { return new TotpVerifier($this->state); }
    private function loginService(bool $expiry = true): LoginService
    {
        return new LoginService(new UserRepository($expiry), $this->limiter(), $this->challenges(), $this->totp());
    }

    public function browserBinding(): string
    {
        $request = $this->app->getCurrentRequest();
        if ($this->binding !== null && $this->bindingRequest === $request) return $this->binding;
        $this->bindingRequest = $request;
        $cookies = $request?->getCookieParams() ?? $_COOKIE;
        $binding = $cookies['light_auth_binding'] ?? null;
        if (is_string($binding) && preg_match('/^[a-f0-9]{64}$/D', $binding)) {
            $revoked = $this->state->update('binding_revoked:' . $binding, self::TTL, static fn (array &$state): bool => ($state['expires'] ?? 0) > time());
            if (!$revoked) return $this->binding = $binding;
        }
        $this->binding = bin2hex(random_bytes(32));
        $this->writeBindingCookie($this->binding);
        return $this->binding;
    }

    public function rotateBrowserBinding(): void
    {
        $old = $this->browserBinding();
        $this->state->update('binding_revoked:' . $old, self::TTL, static function (array &$state): void { $state = ['expires' => time() + self::TTL]; });
        $this->binding = bin2hex(random_bytes(32));
        $this->writeBindingCookie($this->binding);
    }

    private function writeBindingCookie(string $binding): void
    {
        $request = $this->app->getCurrentRequest();
        setcookie('light_auth_binding', $binding, [
            'path' => '/', 'domain' => $_ENV['COOKIE_DOMAIN'] ?? '',
            'secure' => filter_var($_ENV['COOKIE_SECURE'] ?? false, FILTER_VALIDATE_BOOL) || $request?->getUri()->getScheme() === 'https',
            'httponly' => true, 'samesite' => $_ENV['COOKIE_SAMESITE'] ?? 'Lax',
        ]);
    }

    public function login(string $username, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $service = $this->loginService();
        $result = $service->password($username, $password, $ip, $this->browserBinding());
        if ($result->status === Status::Locked) throw new Error('user is locked for ' . Config::Value('auth_lockout_duration', '15') . ' minutes');
        if ($result->status === Status::InvalidCredentials) {
            if ($user = User::Get(['username' => strtolower(trim($username))])) {
                Sql::insert('UserLog', ['user_id' => $user->user_id, 'login_dt' => date('Y-m-d H:i:s'), 'result' => 'FAIL', 'ip' => $ip, 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
            }
            throw new Error('user not found or password error');
        }
        if ($result->status === Status::PasswordExpired) throw new Error('password is expired');
        if ($result->status === Status::SecondFactorRequired) {
            if (!$code) throw new Error('two factor authentication code is required');
            $result = $service->secondFactor($result->challengeToken, $code, $ip, $this->browserBinding());
            if ($result->status !== Status::Authenticated) throw new Error('two factor authentication error');
        }
        $user = $result->userId === null ? null : User::Get($result->userId);
        if (!$user || $user->status != 0) throw new Error('user not found or password error');
        if ($this->app->isTwoFactorAuthentication() && !$user->secret) {
            $this->authorizeSetup($user);
            throw new Error('setup_2fa_required');
        }
        $this->app->userLogin($user);
        $this->rotateBrowserBinding();
        return true;
    }

    /** Password proof for the legacy unauthenticated setup dialog; this does not establish a login. */
    public function authorizeSetupWithPassword(string $username, #[\SensitiveParameter] string $password): ?User
    {
        $result = $this->loginService(false)->password($username, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $this->browserBinding());
        if ($result->status !== Status::Authenticated) return null;
        $user = User::Get($result->userId);
        if (!$user || $user->secret) return null;
        $this->authorizeSetup($user);
        return $user;
    }

    private function authorizeSetup(User $user): void
    {
        $this->remember('totp_authorized', (string) $user->user_id, ['version' => hash('sha256', $user->password)]);
    }

    private function canSetup(User $user): bool
    {
        $authenticated = $this->app->getAuthService()->getUser();
        if ($authenticated && (string) $authenticated->user_id === (string) $user->user_id) return true;
        $proof = $this->recall('totp_authorized', (string) $user->user_id);
        return $proof !== null && hash_equals($proof['version'], hash('sha256', $user->password));
    }

    /** @return array{secret: string, host: string, image: string, setupToken: string} */
    public function beginTotp(User $user): array
    {
        if ($user->isTwoFactorEnabled()) throw new Error('Two-factor authentication is already enabled');
        if (!$this->canSetup($user)) throw new Error('Password verification is required to set up two-factor authentication');
        $host = $this->app->getRpId();
        $setup = $this->enrollment()->begin((string) $user->user_id, $user->username, $host, $this->browserBinding());
        parse_str(parse_url($setup->provisioningUri, PHP_URL_QUERY), $parameters);
        $this->remember('totp_setup', (string) $user->user_id, ['token' => $setup->setupToken, 'secretHash' => hash('sha256', $parameters['secret'])]);
        $image = (new PngWriter())->write(QrCode::create($setup->provisioningUri))->getDataUri();
        return ['secret' => $parameters['secret'], 'host' => $host, 'image' => $image, 'setupToken' => $setup->setupToken];
    }

    public function confirmTotp(User $user, #[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code): bool
    {
        if ($user->isTwoFactorEnabled()) throw new Error('Two-factor authentication is already enabled');
        $pending = $this->recall('totp_setup', (string) $user->user_id);
        if (!$pending || !hash_equals($pending['secretHash'], hash('sha256', $secret))) return false;
        $confirmed = $this->enrollment()->confirm((string) $user->user_id, $pending['token'], $code, $this->browserBinding());
        if ($confirmed === null) return false;
        $saved = $this->withUserLock($confirmed->userId, static function (User $current) use ($confirmed): bool {
            if ($current->secret) return false;
            $current->secret = $confirmed->secret;
            $current->save();
            return true;
        });
        $this->forget('totp_setup', (string) $user->user_id);
        $this->forget('totp_authorized', (string) $user->user_id);
        return $saved;
    }

    private function enrollment(): TotpEnrollment
    {
        return new TotpEnrollment(new UserRepository(), $this->state, $this->policy(), $this->totp());
    }

    public function verifyTotp(User $user, #[\SensitiveParameter] string $code): bool
    {
        return $this->totp()->verify((new UserRepository(false))->map($user), $code);
    }

    public function verifyCredentials(User $user, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $code): bool
    {
        $service = $this->loginService(false);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $result = $service->password($user->username, $password, $ip, $this->browserBinding());
        if ($result->status !== Status::SecondFactorRequired) return false;
        return $service->secondFactor($result->challengeToken, $code, $ip, $this->browserBinding())->status === Status::Authenticated;
    }

    private function credentials(): PdoCredentialRepository { return new PdoCredentialRepository($this->app->getDatabase()->connection()); }

    /** @return list<string> */
    public function allowedOrigins(): array
    {
        $configured = trim($_ENV['WEBAUTHN_ALLOWED_ORIGINS'] ?? '');
        return $configured === '' ? ['https://' . $this->app->getRpId()] : array_values(array_filter(array_map('trim', explode(',', $configured))));
    }

    private function passkeys(): WebAuthnService
    {
        return new WebAuthnService(new UserRepository(), $this->credentials(), $this->challenges(), $this->limiter(), $this->app->getRpId(), $this->allowedOrigins());
    }

    private function passkeyEnrollment(): WebAuthnEnrollment
    {
        return new WebAuthnEnrollment(new UserRepository(), $this->credentials(), $this->challenges(), $this->limiter(), $this->app->getRpId(), $this->app->getRpId(), $this->allowedOrigins());
    }

    /** @return array<string, mixed> */
    public function beginRegistration(User $user): array
    {
        $request = $this->passkeyEnrollment()->begin((string) $user->user_id, $user->getName() ?: $user->username, $this->browserBinding());
        $this->remember('registration', (string) $user->user_id, ['token' => $request->token]);
        return $request->publicKey;
    }

    /** @param array<string, mixed> $registration */
    public function completeRegistration(User $user, array $registration): bool
    {
        $id = (string) $user->user_id;
        $pending = $this->recall('registration', $id, true);
        if (!$pending) throw new Error('Invalid challenge');
        $verified = $this->passkeyEnrollment()->confirm($id, $pending['token'], $registration, $this->browserBinding());
        if (!$verified) throw new Error('Invalid credential');
        $saved = $this->state->update('webauthn_registration_storage', self::TTL, function (array &$state) use ($verified): bool {
            return $this->withUserLock($verified->userId, function (User $current) use ($verified): bool {
                if ($this->credentials()->find($verified->id) !== null) return false;
                $items = $current->credential ?? [];
                $items[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user-agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'timestamp' => time(),
                    'credential' => json_decode($verified->record, true, 512, JSON_THROW_ON_ERROR),
                ];
                $current->credential = $items;
                $current->save();
                return true;
            });
        });
        if (!$saved) throw new Error('Invalid credential');
        return true;
    }

    public function deleteCredential(User $user, string $uuid): bool
    {
        return $this->state->update('webauthn_registration_storage', self::TTL, function (array &$state) use ($user, $uuid): bool {
            return $this->withUserLock((string) $user->user_id, static function (User $current) use ($uuid): bool {
                $current->credential = array_values(array_filter($current->credential ?? [], static fn (array $item): bool => $item['uuid'] !== $uuid));
                $current->save();
                return true;
            });
        });
    }

    /** @return array<string, mixed> */
    public function beginAssertion(): array
    {
        $request = $this->passkeys()->begin($this->browserBinding());
        $this->remember('assertion', '', ['token' => $request->token]);
        return $request->publicKey;
    }

    /** @param array<string, mixed> $assertion */
    public function completeAssertion(array $assertion): bool
    {
        $pending = $this->recall('assertion', '', true);
        if (!$pending) throw new Error('Invalid challenge');
        $result = $this->passkeys()->complete($pending['token'], $assertion, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $this->browserBinding());
        if ($result->status !== Status::Authenticated) throw new Error('Invalid credential');
        $user = User::Get($result->userId);
        if (!$user || $user->status != 0) throw new Error('Invalid user');
        $this->app->userLogin($user);
        $this->rotateBrowserBinding();
        return true;
    }

    /** @param callable(User): bool $save */
    private function withUserLock(string $id, callable $save): bool
    {
        $database = $this->app->getDatabase();
        return $database->transaction(static function () use ($database, $id, $save): bool {
            $query = $database->connection()->prepare('SELECT user_id FROM `User` WHERE user_id = ? FOR UPDATE');
            $query->execute([$id]);
            $user = $query->fetchColumn() === false ? null : User::Get($id);
            return $user && $user->status == 0 ? $save($user) : false;
        });
    }

    /** @param array<string, mixed> $data */
    private function remember(string $purpose, string $id, array $data): void
    {
        $key = $this->pointerKey($purpose, $id);
        $this->state->update($key, self::TTL, static function (array &$state) use ($data): void { $state = ['data' => $data, 'expires' => time() + self::TTL]; });
    }

    /** @return array<string, mixed>|null */
    private function recall(string $purpose, string $id, bool $consume = false): ?array
    {
        $key = $this->pointerKey($purpose, $id);
        return $this->state->update($key, self::TTL, static function (array &$state) use ($consume): ?array {
            $data = ($state['expires'] ?? 0) > time() ? $state['data'] : null;
            if ($consume || $data === null) $state = [];
            return $data;
        });
    }

    private function forget(string $purpose, string $id): void { $this->recall($purpose, $id, true); }
    private function pointerKey(string $purpose, string $id): string { return 'framework:' . $purpose . ':' . hash('sha256', $id . ':' . $this->browserBinding()); }
}
