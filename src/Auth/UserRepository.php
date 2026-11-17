<?php
declare(strict_types=1);

namespace Light\Framework\Auth;

use Light\Auth\Contract\UserRepository as Repository;
use Light\Auth\User as AuthUser;
use Light\Framework\Model\{Config, User};

/** Maps existing Framework users and policy without coupling light-auth to models or Config. */
final class UserRepository implements Repository
{
    public function __construct(private bool $checkPasswordExpiry = true) {}

    public function findByUsername(string $username): ?AuthUser
    {
        return $this->map(User::Get(['username' => $username]));
    }

    public function findById(string $id): ?AuthUser { return $this->map(User::Get($id)); }

    public function map(?User $user): ?AuthUser
    {
        if (!$user) return null;
        $expires = null;
        if ($this->checkPasswordExpiry && Config::Value('password_expiration')) {
            $expires = (strtotime($user->password_dt ?: '1970-01-01') ?: 0) + (int) Config::Value('password_expiration_duration', '90') * 86400;
        }
        $hash = $user->password ?: null;
        // These legacy hashes already required administrator reset before this migration.
        if ($hash !== null && in_array(substr($hash, 0, 2), ['$5', '$6'], true)) $hash = null;
        return new AuthUser((string) $user->user_id, $user->username, $hash, $user->status != 0, $expires, $user->secret ?: null);
    }
}
