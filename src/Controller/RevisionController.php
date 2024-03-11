<?php

namespace Light\Controller;

use Light\App as LightApp;
use Light\Model\Config;
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
    #[Logged]
    #[Query(outputType: "[Revision]")]
    public function getRevisionsByModel(string $model_class, int $model_id): array
    {

        return \Light\Model\Revision::Query(["model_class" => $model_class, "model_id" => $model_id])->toArray();
    }

    
}
