<?php

namespace Light\Model;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Light\App;
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
        return MyFavorite::Query(["user_id" => $this->user_id])->toArray();
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

    public function saveLastAccessTime(string $jti)
    {
        UserLog::_table()->update(["last_access_time" => date("Y-m-d H:i:s")], ["jti" => $jti]);
    }

    public function isAuthLocked()
    {
        $ip = $_SERVER["REMOTE_ADDR"];
        $total = 0;
        $q = UserLog::Query(["user_id" => $this->user_id, "ip" => $ip]);
        // within 180 seconds 

        $auth_lock_time = intval(Config::Value("auth_lockout_duration", 15));  // default 15 minutes

        $auth_lockout_attempts = intval(Config::Value("auth_lockout_attempts", 5));  // default 5 attempts

        $q->where->greaterThan("login_dt", date("Y-m-d H:i:s", time() - ($auth_lock_time * 60)));
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
        if ($user = $rbac->getUser($this->user_id)) {
            foreach ($rights as $right) {
                if ($user->can($right)) {
                    $result[] = $right;
                }
            }
        }
        return $result;
    }

    #[Field]
    public function isGranted(#[Autowire] App $app, string $right): bool
    {
        $rbac = $app->getRbac();

        if ($user = $rbac->getUser($this->user_id)) {
            return $user->can($right);
        }
        return false;
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
    public function getUserLog($filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return UserLog::Query(["user_id" => $this->user_id])->filters($filters)->sort($sort);
    }

    #[Field]
    /**
     * @return EventLog[]
     * @param ?mixed $filters
     */
    public function getEventLog($filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return EventLog::Query(["user_id" => $this->user_id])->filters($filters)->sort($sort);
    }
}
