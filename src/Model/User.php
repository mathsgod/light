<?php

namespace Light\Model;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Light\App;
use Light\Model\Notification;
use Light\Rbac\Rbac;
use Light\Security\TwoFactorAuthentication;
use Light\Type\WebAuthn;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\FailWith;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * @property int $user_id
 * @property string $username
 * @property string $first_name
 * @property string $last_name
 * @property array $credential
 * @property string $secret
 * @property array $style
 */

#[Type]
#[MagicField(name: "user_id", outputType: "Int")]
#[MagicField(name: "username", outputType: "String")]
#[MagicField(name: "first_name", outputType: "String")]
#[MagicField(name: "last_name", outputType: "String")]
#[MagicField(name: "email", outputType: "String")]
#[MagicField(name: "phone", outputType: "String")]
#[MagicField(name: "addr1", outputType: "String")]
#[MagicField(name: "addr2", outputType: "String")]
#[MagicField(name: "addr3", outputType: "String")]
#[MagicField(name: "join_date", outputType: "String")]
#[MagicField(name: "expiry_date", outputType: "String")]
#[MagicField(name: "status", outputType: "Int")]
#[MagicField(name: "language", outputType: "String")]
#[MagicField(name: "default_page", outputType: "String")]
#[MagicField(name: "google", outputType: "String")]
#[MagicField(name: "microsoft", outputType: "String")]
#[MagicField(name: "facebook", outputType: "String")]


class User extends \Light\Model
{
    public function revokeSession(string $jti): bool
    {
        UserLog::_table()->update(["logout_dt" => date("Y-m-d H:i:s")], ["jti" => $jti, "user_id" => $this->user_id]);
        return true;
    }

    #[Field(outputType: "[mixed]")]
    public function getSessions(#[Autowire] App $app): array
    {
        $token_expire = $app->getAccessTokenExpire();
        $jti = $app->getAuthService()->getJti();

        $ul = UserLog::Query(["user_id" => $this->user_id, "result" => "SUCCESS"]);
        $ul->where->isNull("logout_dt");
        $ul->where->greaterThan("login_dt", date("Y-m-d H:i:s", time() - $token_expire));

        $sessions = [];
        foreach ($ul as $log) {

            $sessions[] = [
                "is_current" => $log->jti == $jti,
                "jti" => $log->jti,
                "ip" => $log->ip,
                "login_dt" => $log->login_dt,
                "user_agent" => $log->user_agent,
                "location" => $app->getIpLocation($log->ip),
                "last_access_time" => $log->last_access_time
            ];
        }


        return $sessions;
    }

    #[Field]
    /**
     * @return mixed
     */
    public function getMenu(): array
    {
        if ($this->menu) {
            return $this->menu;
        }
        return [];
    }

    #[Field]
    public function isAllowedPath(string $path, #[Autowire] App $app): bool
    {
        if ($path == "/") return true;

        // Static page-level permission overrides (not derived from menus)
        $pagePermissions = [
            '/User/profile' => ['user.self'],
            '/User/setting' => ['user.self'],
        ];
        if (isset($pagePermissions[$path])) {
            $permissions = $pagePermissions[$path];
        } else {
            foreach ($pagePermissions as $pagePath => $required) {
                if (str_starts_with($path, rtrim($pagePath, "/") . "/")) {
                    $permissions = $required;
                    break;
                }
            }
        }

        if (!empty($permissions)) {
            $rbac = $app->getRbac();
            $rbacUser = $rbac->getUser($this->user_id);

            // If the user isn't in RBAC yet, register them with their roles so
            // permission checks work for users without explicit UserRole rows.
            if (!$rbacUser) {
                $rbacUser = $rbac->addUser((string) $this->user_id, $this->getRoles());
            }

            foreach ($permissions as $permission) {
                if ($rbacUser->can($permission)) {
                    return true;
                }
            }
            return false;
        }

        $permissions = [];
        $matchedMenu = false;
        $matchedPrefix = false;
        foreach ($app->getFlatMenus() as $m) {
            $menuPath = $m["to"] ?? "";
            if ($menuPath === $path) {
                $matchedMenu = true;
                if ($m["permission"]) {
                    $permissions = is_array($m["permission"]) ? $m["permission"] : [$m["permission"]];
                }
            } elseif ($menuPath && str_starts_with($path, rtrim($menuPath, "/") . "/")) {
                // path is a sub-page of an accessible menu item
                $matchedPrefix = true;
                if ($m["permission"]) {
                    $permissions = is_array($m["permission"]) ? $m["permission"] : [$m["permission"]];
                }
            }
        }

        // If the path is under a menu item without an explicit permission, allow access
        if (($matchedMenu || $matchedPrefix) && empty($permissions)) {
            return true;
        }

        // fallback: derive module-level wildcard permission from path segments
        if (empty($permissions)) {
            $parts = explode("/", trim($path, "/"));
            if (count($parts) >= 1) {
                $module = strtolower($parts[0]);
                $permissions = [$module . ".*"];
            }
        }

        //if no permission is required, allow access
        if (empty($permissions)) {
            return true;
        }

        $rbac = $app->getRbac();
        $rbacUser = $rbac->getUser($this->user_id);

        // If the user isn't in RBAC yet, register them with their roles so
        // permission checks work for users without explicit UserRole rows.
        if (!$rbacUser) {
            $rbacUser = $rbac->addUser((string) $this->user_id, $this->getRoles());
        }

        foreach ($permissions as $permission) {
            if ($rbacUser->can($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return ?mixed
     */
    #[Field]
    public function getMy2FA()
    {

        $secret = (new TwoFactorAuthentication())->generateSecret();

        $host = $_SERVER["HTTP_HOST"];
        $url = sprintf("otpauth://totp/%s@%s?secret=%s", $this->username, $host, $secret);

        $writer = new PngWriter();
        $png = $writer->write(QrCode::create($url));
        return [
            "secret" => $secret,
            "host" => $host,
            "image" => $png->getDataUri()
        ];
    }


    #[Field]
    /**
     * @return \Light\Type\WebAuthn[]
     */
    public function getWebAuthn(): array
    {
        $data = [];
        foreach ($this->credential as $credential) {
            $data[] = new WebAuthn($credential);
        }
        return $data;
    }


    #[Field]
    public function has2FA(): bool
    {
        return $this->secret ? true : false;
    }

    #[Field]
    /**
     * @return MyFavorite[]
     */
    public function getMyFavorites(#[Autowire] App $app)
    {
        if (!$app->hasFavorite()) return [];
        return MyFavorite::Query(["user_id" => $this->user_id])->sort("sequence:asc")->toArray();
    }

    public function addMyFavorite(string $label, string $path)
    {
        $myfav = MyFavorite::Create([
            "user_id" => $this->user_id,
            "label" => $label,
            "path" => $path,
        ]);
        $myfav->save();
        return $myfav;
    }

    #[Field]
    /**
     * @return string[]
     */
    public function getPermissions(#[Autowire] App $app): array
    {
        $rbac = $app->getRbac();
        if ($u = $rbac->getUser($this->user_id)) {
            return $u->getPermissions();
        }
        return [];
    }

    #[Field]
    public function getUnreadNotificationCount(): int
    {
        return Notification::Query(['user_id' => $this->user_id, 'is_read' => 0])->count();
    }

    public function saveLastAccessTime(string $jti)
    {
        UserLog::_table()->update(["last_access_time" => date("Y-m-d H:i:s")], ["jti" => $jti]);
    }

    public function isAuthLocked()
    {
        $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
        $total = 0;
        $q = UserLog::Query(["user_id" => $this->user_id, "ip" => $ip]);

        // Lockout window in minutes (default 15)
        $auth_lockout_minutes = intval(Config::Value("auth_lockout_duration", 15));

        $auth_lockout_attempts = intval(Config::Value("auth_lockout_attempts", 5));  // default 5 attempts

        $q->where->greaterThan("login_dt", date("Y-m-d H:i:s", time() - ($auth_lockout_minutes * 60)));
        foreach ($q->order("userlog_id")->limit($auth_lockout_attempts) as $ul) {
            if ($ul->result == "FAIL") {
                $total++;
            }
        }
        if ($total >= $auth_lockout_attempts) {
            return true;
        }
        return false;
    }

    #[Field]
    /**
     * @return mixed
     */
    public function getStyles(): array
    {
        if (is_string($this->style)) {
            return json_decode($this->style, true) ?? [];
        }
        return $this->style ?? [];
    }

    public function updateStyle(string $name, $value): void
    {
        $styles = $this->getStyles();
        $styles[$name] = $value;
        if (is_string($this->style)) {
            $this->style = json_encode($styles);
        } else {
            $this->style = $styles;
        }

        $this->save();
    }

    #[Field]
    public function isTwoFactorEnabled(): bool
    {
        if ($this->secret) {
            return true;
        }
        return false;
    }

    #[Field]
    /**
     * @param string[] $rights
     * @return string[]
     */
    public function isGrantedRights(#[Autowire] App $app, array $rights): array
    {
        $result = [];
        $rbac = $app->getRbac();
        if ($u = $rbac->getUser($this->user_id)) {
            foreach ($rights as $right) {
                if ($u->can($right)) {
                    $result[] = $right;
                }
            }
        }
        return $result;
    }

    #[Field]
    public function isGranted(#[Autowire] App $app, string $right): bool
    {
        return $this->can($right, $app->getRbac());
    }

    /**
     * Check a permission directly in PHP (e.g. from canDelete / canUpdate).
     * Falls back to self::$container when no Rbac is passed.
     */
    public function can(string $right, ?Rbac $rbac = null): bool
    {
        $rbac ??= self::$container?->get(App::class)?->getRbac();
        if (!$rbac) return false;
        $u = $rbac->getUser($this->user_id);
        if (!$u) return false;
        return $u->can($right);
    }

    #[Field]
    /**
     * @return string[]
     */
    public function getRoles(): array
    {

        $q = UserRole::Query(["user_id" => $this->user_id]);
        $roles = [];
        foreach ($q as $r) {
            $roles[] = $r->role;
        }

        if (empty($roles)) {
            $roles[] = "Everyone";
        }

        return $roles;
    }


    #[Field]
    #[Right("user.delete")]
    #[FailWith(value: false)]
    public function canDelete(#[InjectUser] ?User $by): bool
    {
        //user cannot delete himself
        if ($by && $by->user_id == $this->user_id) {
            return false;
        }

        //only administrators can delete administrators
        if ($this->is("Administrators") && !$by->is("Administrators")) {
            return false;
        }

        //administrators can delete everyone
        if ($by->is("Administrators") || $by->is("Power Users")) {
            return true;
        }

        return false;
    }

    public function is(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }


    #[Field]
    public function getName(): string
    {
        return trim($this->first_name . " " . $this->last_name);
    }

    #[Field]
    public function canUpdate(#[InjectUser] ?User $by): bool
    {
        //user can update himself
        if ($by && $by->user_id == $this->user_id) {
            return true;
        }

        //only administrators can update administrators
        if ($this->is("Administrators") && !$by->is("Administrators")) {
            return false;
        }

        //administrators can update everyone
        if ($by->is("Administrators") || $by->is("Power Users")) {
            return true;
        }

        return false;
    }

    #[Field]
    /**
     * @return UserLog[]
     * @param ?mixed $filters
     */
    public function getUserLog($filters = null,  ?string $sort = ''): \Light\Db\Query
    {
        return UserLog::Query(["user_id" => $this->user_id])->filters($filters)->sort($sort);
    }

    #[Field]
    /**
     * @return EventLog[]
     * @param ?mixed $filters
     */
    public function getEventLog($filters = null,  ?string $sort = ''): \Light\Db\Query
    {
        return EventLog::Query(["user_id" => $this->user_id])->filters($filters)->sort($sort);
    }
}
