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

    #[Field()]
    #[Right("system.database.export")]
    public function export(#[Autowire] App $app): string
    {
        $username = $_ENV["DATABASE_USERNAME"];
        $password = $_ENV["DATABASE_PASSWORD"];
        $database = $_ENV["DATABASE_DATABASE"];
        $host = $_ENV["DATABASE_HOSTNAME"];
        $port = $_ENV["DATABASE_PORT"];

        $command = "mysqldump -u $username -p$password --databases $database --host $host --port $port";

        //exec
        $output = [];
        exec($command, $output);

        return implode("\n", $output);
    }
}
