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
     * @return UserLog[]
     * @param ?mixed $filters
     */
    #[Right("userlog.list")]
    public function listUserLog($filters = [],  ?string $sort = '', #[InjectUser] \Light\Model\User $user): \R\DB\Query
    {
        return UserLog::Query()->filters($filters)->sort($sort);
    }
}
