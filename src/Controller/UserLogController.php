<?php

namespace Light\Controller;

use Light\Model\UserLog;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class UserLogController
{
    #[Query]
    #[Logged]
    /**
     * @return \Light\Model\UserLog[]
     * @param ?mixed $filters
     * @deprecated use { app { userlogs }}
     */
    #[Right("userlog.list")]
    public function listUserLog(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return UserLog::Query()->filters($filters)->sort($sort);
    }
}
