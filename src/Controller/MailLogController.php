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
     * @return \Light\Model\MailLog[]
     * @param ?mixed $filters
     */
    #[Right("maillog:list")]
    public function listMailLog(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = ''): \R\DB\Query
    {
        return MailLog::Query()->filters($filters)->sort($sort);
    }
}
