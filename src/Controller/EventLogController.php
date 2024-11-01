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
    /**
     * @return \Light\Model\EventLog[]
     * @param ?mixed $filters
     * @deprecated use app.eventLogs instead
     */
    #[Right("eventlog.list")]
    public function listEventLog(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = ''): \R\DB\Query
    {
        return EventLog::Query()->filters($filters)->sort($sort);
    }
}
