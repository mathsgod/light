<?php

namespace Light\Controller;

use Exception;
use Firebase\JWT\JWT;
use Google\Service\MigrationCenterAPI\UploadFileInfo;
use GraphQL\Error\Error;
use JsonToSql;
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


    /*    #[Mutation]
    #[Right("system.database.event:alter")]
    public function alterDatabaseEvent(#[Autowire] App $app, string $name, string $body): bool
    {
        $db = $app->getDatabase();
        try {
            $db->query("ALTER EVENT $name DO $body", $db::QUERY_MODE_EXECUTE);
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        return true;
    }
 */

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
    #[Right("system.database.restore")]
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

    #[Mutation]
    #[Right("system.database.fix")]
    public function fixDatabaseTable(#[Autowire] App $app, string $name): bool
    {
        $db = $app->getDatabase();

        $schema = new \Light\Database\Schema();

        $results = $schema->checkResult($app);

        // Find the table result
        $tableResult = null;
        foreach ($results as $result) {
            if ($result['table'] === $name) {
                $tableResult = $result;
                break;
            }
        }

        if (!$tableResult) {
            throw new Error("Table '$name' not found in schema");
        }

        // If table doesn't exist, we can't fix it
        if (!$tableResult['exists']) {

            // Create table from schema

            $schema = file_get_contents(__DIR__ . "/../../db.json");
            //find table schema
            $tableSchema = json_decode($schema, true);
            $tableSchema = array_filter($tableSchema, fn($t) => $t['name'] === $name);
            $tableSchema = $tableSchema ? array_values($tableSchema)[0] : null;

            if (!$tableSchema) {
                throw new Error("Table '$name' not found in schema");
            }

            $jtq = new JsonToSql();
            $sql = $jtq->generateCreateTableStatement($tableSchema);

            //execute sql
            try {
                $db->query($sql, $db::QUERY_MODE_EXECUTE);
            } catch (Exception $e) {
                throw new Error("Failed to create table '$name': " . $e->getMessage());
            }
            return true;
        }

        // Process differences and apply fixes
        foreach ($tableResult['differences'] as $diff) {
            try {
                if ($diff['type'] === 'missing_column') {
                    // Add missing column
                    $colDef = $diff['expected'];
                    $columnType = strtoupper($colDef['type']);
                    $length = $colDef['length'] ?? null;
                    $nullable = $colDef['nullable'] ?? true ? 'NULL' : 'NOT NULL';
                    $default = $colDef['default'] ?? null;
                    $unsigned = $colDef['unsigned'] ?? false ? 'UNSIGNED' : '';
                    $autoIncrement = $colDef['auto_increment'] ?? false ? 'AUTO_INCREMENT' : '';

                    $sql = "ALTER TABLE `$name` ADD `{$diff['column']}` $columnType";

                    if ($length) {
                        $sql .= "($length)";
                    }

                    if ($unsigned) {
                        $sql .= " $unsigned";
                    }

                    $sql .= " $nullable";

                    if ($default !== null) {
                        $sql .= " DEFAULT '$default'";
                    }

                    if ($autoIncrement) {
                        $sql .= " $autoIncrement";
                    }

                    $db->query($sql, $db::QUERY_MODE_EXECUTE);
                } elseif ($diff['type'] === 'column_mismatch') {
                    // Modify column to match expected definition
                    $colDef = $diff['differences'];
                    $expectedDef = null;

                    // Get expected definition from db.json
                    $tables = json_decode(file_get_contents(__DIR__ . "/../../db.json"), true);
                    foreach ($tables as $tableSchema) {
                        if ($tableSchema['name'] === $name) {
                            foreach ($tableSchema['columns'] as $col) {
                                if ($col['name'] === $diff['column']) {
                                    $expectedDef = $col;
                                    break;
                                }
                            }
                            break;
                        }
                    }

                    if ($expectedDef) {
                        $columnType = strtoupper($expectedDef['type']);
                        $length = $expectedDef['length'] ?? null;
                        $nullable = $expectedDef['nullable'] ?? true ? 'NULL' : 'NOT NULL';
                        $default = $expectedDef['default'] ?? null;
                        $unsigned = $expectedDef['unsigned'] ?? false ? 'UNSIGNED' : '';

                        $sql = "ALTER TABLE `$name` MODIFY `{$diff['column']}` $columnType";

                        if ($length) {
                            $sql .= "($length)";
                        }

                        if ($unsigned) {
                            $sql .= " $unsigned";
                        }

                        $sql .= " $nullable";

                        if ($default !== null) {
                            $sql .= " DEFAULT '$default'";
                        }

                        $db->query($sql, $db::QUERY_MODE_EXECUTE);
                    }
                } elseif ($diff['type'] === 'extra_column') {
                    // Remove extra column
                    $db->query("ALTER TABLE `$name` DROP COLUMN `{$diff['column']}`", $db::QUERY_MODE_EXECUTE);
                }
            } catch (Exception $e) {
                throw new Error("Failed to fix column '{$diff['column']}': {$e->getMessage()}");
            }
        }

        return true;
    }
}
