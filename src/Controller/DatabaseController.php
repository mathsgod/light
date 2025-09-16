<?php

namespace Light\Controller;

use Exception;
use Firebase\JWT\JWT;
use Google\Service\MigrationCenterAPI\UploadFileInfo;
use GraphQL\Error\Error;
use Laminas\Db\Sql\Ddl\CreateTable;
use Light\App;
use Light\Type\System;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use Laminas\Db\Sql\Ddl\Column;
use Psr\Http\Message\UploadedFileInterface;

class DatabaseController
{

    #[Mutation(name: "lightDatabaseTruncateTable")]
    #[Right("system.database.table.truncate")]
    public function truncateDatabaseTable(#[Autowire] App $app, string $table): bool
    {
        $db = $app->getDatabase();
        try {
            $db->getTable($table)->truncate();
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        return true;
    }

    #[Mutation(name: "lightDatabaseRemoveTable")]
    #[Right("system.database.table.remove")]
    public function removeDatabaseTable(#[Autowire] App $app, string $table): bool
    {
        $db = $app->getDatabase();
        try {
            $db->removeTable($table);
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        return true;
    }


    #[Mutation(name: "lightDatabaseAddField")]
    #[Right("system.database.field.add")]
    public function addDatabaseField(#[Autowire] App $app, string $table, string $field, string $type, string $length, string $default, bool $nullable, bool $autoincrement): bool
    {
        $db = $app->getDatabase();
        try {
            $db->query("ALTER TABLE $table ADD $field $type($length) DEFAULT '$default' " . ($nullable ? "NULL" : "NOT NULL") . " " . ($autoincrement ? "AUTO_INCREMENT" : ""), $db::QUERY_MODE_EXECUTE);
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        return true;
    }


    #[Mutation(name: "lightDatabaseRemoveFields")]
    #[Right("system.database.field.remove")]
    /**
     * @param string[] $fields
     */
    public function removeDatabaseFields(#[Autowire] App $app, string $table, array $fields): bool
    {
        $db = $app->getDatabase();
        foreach ($fields as $field) {
            try {
                $db->query("ALTER TABLE $table DROP COLUMN $field", $db::QUERY_MODE_EXECUTE);
            } catch (Exception $e) {
                throw new Error($e->getMessage());
            }
        }
        return true;
    }


    #[Mutation(name: "lightDatabaseCreateTable")]
    #[Right("system.database.table.create")]
    #[Logged]
    /**
     * @param \Light\Input\Table\Column[] $fields
     */
    public function createDatabaseTable(#[Autowire] App $app, string $name, array $fields): bool
    {
        $db = $app->getDatabase();

        $t = new CreateTable($name);
        foreach ($fields as $column) {
            if ($column->type == "int") {
                $t->addColumn(new Column\Integer($column->name, $column->nullable ?? false));
            }


            if ($column->type == "varchar") {
                $t->addColumn(new Column\Varchar($column->name, $column->length ?? 255, $column->nullable ?? false));
            }

            if ($column->type == "text") {
                $t->addColumn(new Column\Text($column->name, $column->length ?? 4000, $column->nullable ?? false));
            }

            if ($column->type == "boolean") {
                $t->addColumn(new Column\Boolean($column->name, $column->nullable ?? false));
            }

            if ($column->type == "date") {
                $t->addColumn(new Column\Date($column->name, $column->nullable ?? false));
            }
        }

        try {
            $db->query($t->getSqlString($db->getPlatform()), $db::QUERY_MODE_EXECUTE);
        } catch (\Exception $e) {
            throw new Error($e->getMessage());
        }
        return true;
    }

    #[Mutation]
    #[Right("system.database.import")]
    public function restoreDatabase(#[Autowire] App $app, UploadedFileInterface $file): bool
    {

        $username = $_ENV["DATABASE_USERNAME"];
        $password = $_ENV["DATABASE_PASSWORD"];
        $database = $_ENV["DATABASE_DATABASE"];
        $host = $_ENV["DATABASE_HOSTNAME"];
        $port = $_ENV["DATABASE_PORT"];

        $command = "mysql -u $username -p$password --database $database --host $host --port $port < " . $file->getStream()->getMetadata("uri");

        //exec
        $output = [];
        exec($command, $output);

        return true;
    }
}
