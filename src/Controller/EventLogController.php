<?php

namespace Light\Controller;

use Google\Service\AIPlatformNotebooks\Event;
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
    public function listEventLog(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        $app = new \Light\Type\App($filters, $sort);
        return $app->listEventLog($filters, $sort);
    }
}
