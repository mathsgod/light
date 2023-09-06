<?php

namespace Light\Controller;

use Light\Model\MailLog;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class MailLogController
{
    #[Query]
    #[Logged]
    /**
     * @return MailLog[]
     * @param ?mixed $filters
     */
    #[Right("maillog.list")]
    public function listMailLog($filters = [],  ?string $sort = '', #[InjectUser] \Light\Model\User $user): \R\DB\Query
    {
        return MailLog::Query()->filters($filters)->sort($sort);
    }
}
