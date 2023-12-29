<?php

namespace Light\Type;

use Light\App;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Database
{
    #[Field(outputType: "mixed")]
    #[Right("system.database.table")]
    public function getTable(#[Autowire] App $app): array
    {
        $db = $app->getDatabase();
        $tables = $db->getTables();
        $data = [];
        foreach ($tables as $table) {
            $data[] = ["name" => $table->getName(), "columns" => $table->getColumns()];
        }
        return $data;
    }
}
