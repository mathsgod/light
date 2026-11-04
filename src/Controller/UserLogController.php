<?php

namespace Light\Framework\Controller;

use Light\Framework\Model\UserLog;
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
     * @return \Light\Framework\Model\UserLog[]
     * @param ?mixed $filters
     * @deprecated use { app { userlogs }}
     */
    #[Right("user_log.list")]
    public function listUserLog(#[InjectUser] \Light\Framework\Model\User $user, $filters = null,  ?string $sort = ''): \Light\Db\Query
    {
        return UserLog::Query()->filters($filters)->sort($sort);
    }
}
