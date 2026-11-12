<?php

// Standalone controller regression test with in-memory dependencies.
// Run: php tests/login-error-regression.php
namespace GraphQL\Error {
    class Error extends \RuntimeException {}
}

namespace Light {
    class App
    {
        public bool $loggedIn = false;

        public function isTwoFactorAuthentication(): bool { return false; }
        public function userLogin($user): void { $this->loggedIn = true; }
    }
}

namespace Light\Model {
    class Config
    {
        public static function Value($name, $default = null) { return $default; }
    }

    class UserLog
    {
        public static array $rows = [];

        public static function _table(): self { return new self(); }
        public function insert(array $row): void { self::$rows[] = $row; }
    }

    class User
    {
        public static ?self $current = null;
        public int $user_id = 1;
        public string $password;
        public ?string $secret = null;
        public bool $locked = false;

        public static function Query(): self { return new self(); }
        public function count(): int { return 1; }
        public static function Get($criteria): ?self { return self::$current; }
        public function isAuthLocked(): bool { return $this->locked; }
    }
}

namespace {
    require __DIR__ . '/../src/Controller/AuthController.php';

    function check(bool $condition, string $description): void
    {
        if (!$condition) {
            throw new \RuntimeException($description);
        }
    }

    $controller = new \Light\Controller\AuthController();
    $app = new \Light\App();
    $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
    $_SERVER['HTTP_USER_AGENT'] = 'login-error-regression';
    $user = new \Light\Model\User();
    $user->password = password_hash('correct-password', PASSWORD_BCRYPT);
    $expected = 'Invalid credentials or account temporarily unavailable';

    foreach (['missing', 'wrong-password', 'locked-wrong-password', 'locked-correct-password'] as $scenario) {
        \Light\Model\User::$current = $scenario === 'missing' ? null : $user;
        $user->locked = str_starts_with($scenario, 'locked-');
        \Light\Model\UserLog::$rows = [];
        $password = $scenario === 'locked-correct-password' ? 'correct-password' : 'wrong-password';

        try {
            $controller->login($app, 'candidate', $password);
            throw new \RuntimeException("$scenario unexpectedly logged in");
        } catch (\GraphQL\Error\Error $error) {
            check($error->getMessage() === $expected, "$scenario exposed a different message");
        }

        check(!$app->loggedIn, "$scenario authenticated the user");
        check(count(\Light\Model\UserLog::$rows) === ($scenario === 'wrong-password' ? 1 : 0), "$scenario changed failed-login logging");
        if ($scenario === 'wrong-password') {
            check(\Light\Model\UserLog::$rows[0]['result'] === 'FAIL', 'Failed login was not recorded');
        }
    }

    $user->locked = false;
    \Light\Model\User::$current = $user;
    check($controller->login($app, 'candidate', 'correct-password'), 'Valid login failed');
    check($app->loggedIn, 'Valid login did not create a session');

    echo "PASS: uniform login errors, preserved lockout and logging, successful login\n";
}
