<?php

namespace Light\Framework\Controller;

use Light\Framework\Model\MailLog;
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
     * @return \Light\Framework\Model\MailLog[]
     * @param ?mixed $filters
     * @deprecated use app.mailLogs instead
     */
    #[Right("maillog.list")]
    public function listMailLog(#[InjectUser] \Light\Framework\Model\User $user, $filters = null,  ?string $sort = ''): \Light\Db\Query
    {
        return MailLog::Query()->filters($filters)->sort($sort);
    }
}
