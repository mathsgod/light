<?php

namespace Light\Controller;

use Light\Model\EventLog;
use Light\Type\Revision;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;

class RevisionController
{

    #[Mutation]
    #[Logged]
    /**
     * @param string[] $fields
     */
    #[Right("revision.restore")]
    public function restoreRevision(int $revision_id, array $fields): bool
    {
        $eventlog = EventLog::Get($revision_id);
        return (new Revision($eventlog))->retoreFields($fields);
    }



    #[Logged]
    #[Query(outputType: "[Revision]")]
    #[Right("revision.read")]
    public function getRevisionsByModel(string $model_class, int $model_id): array
    {

        $events = EventLog::Query(['class' => $model_class, "id" => $model_id])
            ->sort("created_time:desc")
            ->toArray();

        return array_map(function ($event) {
            return new Revision($event);
        }, $events);
    }
}
