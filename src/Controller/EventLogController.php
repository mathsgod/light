<?php

namespace Light\Controller;

use Light\Model\EventLog;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class EventLogController
{
    #[Query]
    #[Logged]
    #[Right("ADMIN")]
    /**
     * @return EventLog[]
     * @param ?mixed $filters
     */
    public function listEventLog($filters = [],  ?string $sort = '', #[InjectUser] \Light\Model\User $user): \R\DB\Query
    {
        return EventLog::Query()->filters($filters)->sort($sort);
    }
}
