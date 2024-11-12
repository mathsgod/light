<?php

namespace Light\Database;

use Light\App;
use Light\Database\Table;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;


#[Type]
class Schema
{
    #[Field(outputType: "[Table]")]
    #[Right("system.database.table")]
    public function getTables(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->query("SHOW TABLE STATUS")->fetchAll();

        $data = [];
        foreach ($result as $row) {
            $data[] = new Table($row["Name"], $row);
        }

        return $data;
    }

    #[Field]
    #[Right("system.database.version")]
    public function getVersion(#[Autowire] App $app): string
    {
        $db = $app->getDatabase();
        $result = $db->query("SELECT VERSION()")->fetchAll();
        return $result[0]["VERSION()"];
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.table")]
    public function getTableStatus(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->query("SHOW TABLE STATUS")->fetchAll();
        return $result;
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.table")]
    public function getTable(#[Autowire] App $app): array
    {
        $db = $app->getDatabase();
        $tables = $db->getTables();
        $data = [];
        foreach ($tables as $table) {

            $name = $table->getName();
            $data[] = [
                "name" => $name,
                "columns" => $table->getColumns()
            ];
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
