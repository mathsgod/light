<?php

namespace Light\Framework\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "userlog_id", outputType: "Int!")]
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "login_dt", outputType: "String!")]
#[MagicField(name: "logout_dt", outputType: "String")]
#[MagicField(name: "ip", outputType: "String")]
#[MagicField(name: "result", outputType: "mixed!")]
#[MagicField(name: "user_agent", outputType: "String")]
#[MagicField(name: "last_access_time", outputType: "String")]

class UserLog extends \Light\Framework\Model
{
    /** @return \Light\Db\Query<self> */
    public static function visibleTo(?User $viewer, ?array $filters = null, ?string $sort = ''): \Light\Db\Query
    {
        $query = self::Query()->filters($filters)->sort($sort);
        if ($viewer === null) {
            return $query->whereRaw('1 = 0');
        }
        if (!$viewer->is('Administrators')) {
            return $query->whereRaw('user_id NOT IN (SELECT user_id FROM UserRole WHERE role = ?)', ['Administrators']);
        }
        return $query;
    }

    #[Field]
    public function getUsername(): ?string
    {
        if ($u = User::Get($this->user_id)) {
            return $u->username;
        }
        return null;
    }
}

UserLog::RegisterFilter("username", static function ($filter, \Light\Db\Query $query) {
    $ids = array_map(static fn ($user) => $user->user_id, User::Query()->filters(['username' => $filter])->toArray());
    return $query->where(['user_id' => $ids]);
});
