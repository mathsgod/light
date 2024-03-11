<?php

namespace Light\Controller;

use Light\App as LightApp;
use Light\Model\Config;
use Light\Model\Revision;
use Light\Model\User;
use Light\Type\App;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use Psr\Http\Message\UploadedFileInterface;

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
        return Revision::Get($revision_id)->retoreFields($fields);
    }



    #[Logged]
    #[Query(outputType: "[Revision]")]
    #[Right("revision.read")]
    public function getRevisionsByModel(string $model_class, int $model_id): array
    {

        return \Light\Model\Revision::Query(["model_class" => $model_class, "model_id" => $model_id])
            ->sort("created_time:desc")
            ->toArray();
    }
}
