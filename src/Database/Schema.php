<?php

namespace Light\Framework\Database;

use Light\Framework\App;
use Light\Framework\Database\Table;
use MysqlSchemaMigrate\Exporter;
use MysqlSchemaMigrate\Importer;
use Psr\Http\Message\UploadedFileInterface;
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

    #[Field]
    #[Right("system.database.schema_import")]
    public function importSchema(UploadedFileInterface $schema): string
    {
        $importer = new Importer(
            host: $_ENV["DATABASE_HOSTNAME"],
            database: $_ENV["DATABASE_DATABASE"],
            username: $_ENV["DATABASE_USERNAME"],
            password: $_ENV["DATABASE_PASSWORD"],
            port: (int)$_ENV["DATABASE_PORT"] ?: 3306,
            charset: $_ENV["DATABASE_CHARSET"] ?? 'utf8mb4'
        );

        // Import from file (dry-run)
        $importer->importFromFile(
            filePath: $schema->getStream()->getMetadata("uri"),
            dryRun: true,
            allowDrop: false,
            keepDefiner: false,
            only: [] // empty = all types
        );

        return $importer->formatSqlForFile();
    }

    #[Field]
    #[Right("system.database.schema_export")]
    public function exportSchema(): string
    {

        $exporter = new Exporter(
            host: $_ENV["DATABASE_HOSTNAME"],
            database: $_ENV["DATABASE_DATABASE"],
            username: $_ENV["DATABASE_USERNAME"],
            password: $_ENV["DATABASE_PASSWORD"],
            port: (int)$_ENV["DATABASE_PORT"] ?: 3306,
            charset: $_ENV["DATABASE_CHARSET"] ?? 'utf8mb4'
        );

        return $exporter->toJson();
    }

    #[Field]
    #[Right("system.database.events")]
    /**
     * @return mixed
     */
    public function getEvents(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->connection()->query("SHOW EVENTS")->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.metadata")]
    public function getProcedures(#[Autowire] App $app): array
    {
        return $this->listRoutines($app, 'PROCEDURE');
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.metadata")]
    public function getFunctions(#[Autowire] App $app): array
    {
        return $this->listRoutines($app, 'FUNCTION');
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.metadata")]
    public function getProcedure(#[Autowire] App $app, string $name): array
    {
        return $this->getRoutineDefinition($app, 'PROCEDURE', $name);
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.metadata")]
    public function getFunction(#[Autowire] App $app, string $name): array
    {
        return $this->getRoutineDefinition($app, 'FUNCTION', $name);
    }

    private function getRoutineDefinition(App $app, string $type, string $name): array
    {
        try {
            $definition = $app->getDatabase()->connection()
                ->query('SHOW CREATE ' . $type . ' ' . Sql::identifier($name))
                ->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1305) {
                throw new \GraphQL\Error\UserError('Routine not found.');
            }
            throw $e;
        }
        if ($definition === false) {
            throw new \GraphQL\Error\UserError('Routine not found.');
        }
        return $definition;
    }

    private function listRoutines(App $app, string $type): array
    {
        $statement = $app->getDatabase()->connection()->prepare(
            'SELECT ROUTINE_NAME AS name, DATA_TYPE AS return_type, CREATED AS created, LAST_ALTERED AS last_altered '
            . 'FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = ? ORDER BY ROUTINE_NAME'
        );
        $statement->execute([$type]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    #[Field]
    #[Right("system.database.event")]
    /**
     * @return mixed
     */
    public function getEvent(#[Autowire] App $app, string $name)
    {
        $db = $app->getDatabase();
        $result = $db->connection()->query('SHOW CREATE EVENT ' . Sql::identifier($name))->fetchAll(\PDO::FETCH_ASSOC);
        return $result[0];
    }

    #[Field]
    /**
     * @return mixed
     */
    #[Right("system.database.process")]
    public function getProcessList(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->connection()->query("SHOW FULL PROCESSLIST");
        return  $result->fetchAll(\PDO::FETCH_ASSOC);
    }



    #[Field(outputType: "[Table]")]
    #[Right("system.database.table")]
    public function getTables(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->connection()->query("SHOW TABLE STATUS")->fetchAll(\PDO::FETCH_ASSOC);

        $data = [];
        foreach ($result as $row) {
            $data[] = new Table($row["Name"], $row);
        }

        return $data;
    }

    #[Field]
    #[Right("system.database.metadata")]
    public function getType(#[Autowire] App $app): string
    {
        $db = $app->getDatabase();
        $result = $db->connection()->query("SELECT @@version_comment as version_comment")->fetchAll(\PDO::FETCH_ASSOC);
        $data = $result;
        return (string)$data[0]["version_comment"];
    }

    #[Field]
    #[Right("system.database.metadata")]
    public function getSizeBytes(#[Autowire] App $app): int
    {
        $db = $app->getDatabase();
        $result = $db->connection()->query("SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = DATABASE()")->fetchAll(\PDO::FETCH_ASSOC);
        $data = $result;
        return (int)$data[0]["size"];
    }

    #[Field]
    #[Right("system.database.metadata")]
    public function getSize(#[Autowire] App $app): string
    {
        $bytes = $this->getSizeBytes($app);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / (1024 ** $power), 2, '.', ',') . ' ' . $units[(int)$power];
    }

    #[Field]
    #[Right("system.database.version")]
    public function getVersion(#[Autowire] App $app): string
    {
        $db = $app->getDatabase();
        $result = $db->connection()->query("SELECT VERSION()")->fetchAll(\PDO::FETCH_ASSOC);
        return $result[0]["VERSION()"];
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.table")]
    public function getTableStatus(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        return  $db->connection()->query("SHOW TABLE STATUS")->fetchAll(\PDO::FETCH_ASSOC);
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.table")]
    public function getTable(#[Autowire] App $app): array
    {
        $db = $app->getDatabase();
        $data = [];
        foreach ($db->connection()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $name) {
            $data[] = ['name' => $name, 'columns' => array_map(static fn ($column) => [
                'name' => $column['name'], 'type' => $column['type'],
                'default' => $column['default'], 'null' => (bool)$column['nullable'],
                'length' => $column['length'], 'extra' => $column['extra'],
            ], Sql::columns($name))];
        }
        return $data;
    }

    #[Field()]
    #[Right("system.database.export")]
    public function export(#[Autowire] App $app): string
    {
        return Dump::export();
    }


    #[Field()]
    #[Right("system.database.import")]
    public function import(#[Autowire] App $app, UploadedFileInterface $file): bool
    {
        Dump::import($app->getDatabase(), $file->getStream());
        return true;
    }

    #[Field]
    /**
     * Check the database schema against the default schema
     * @return mixed
     */
    public function checkResult(#[Autowire] App $app)
    {
        //read the default schema from db.json
        $tables = json_decode(file_get_contents(__DIR__ . "/../../db.json"), true);

        $db = $app->getDatabase();

        $results = [];

        foreach ($tables as $tableSchema) {
            $tableName = $tableSchema['name'];
            $result = [
                'table' => $tableName,
                'exists' => false,
                'differences' => []
            ];


            // Check if table exists in database
            try {
                $schemaColumns = array_column($tableSchema['columns'], null, 'name');
                $dbTable = Sql::columns($tableName);

                if (!$dbTable) {
                    $result['exists'] = false;
                    $result['status'] = 'MISSING';
                    foreach ($schemaColumns as $colName => $colDef) {
                        $result['differences'][] = [
                            'type' => 'missing_column',
                            'column' => $colName,
                            'expected' => $colDef
                        ];
                    }
                    $results[] = $result;
                    continue;
                }

                $result['exists'] = true;

                // Get current table columns from database
                $currentColumns = [];
                foreach ($dbTable as $col) {
                    $currentColumns[$col['name']] = $col;
                }

                // Compare columns
                $schemaColumns = [];
                foreach ($tableSchema['columns'] as $col) {
                    $schemaColumns[$col['name']] = $col;
                }




                // Check for missing columns in database
                foreach ($schemaColumns as $colName => $colDef) {
                    if (!isset($currentColumns[$colName])) {
                        $result['differences'][] = [
                            'type' => 'missing_column',
                            'column' => $colName,
                            'expected' => $colDef
                        ];
                    } else {

                        // Compare column properties
                        $current = $currentColumns[$colName];

                        $diffs = [];

                        if (strtolower($current['type']) !== strtolower($colDef['type'])) {
                            $diffs['type'] = [
                                'current' => $current['type'],
                                'expected' => $colDef['type']
                            ];
                        }

                        if (isset($colDef['length']) && $current['length'] != $colDef['length']) {
                            $diffs['length'] = [
                                'current' => $current['length'],
                                'expected' => $colDef['length']
                            ];
                        }

                        if (isset($colDef['nullable'])) {
                            $expectedNullable = $colDef['nullable'];
                            if ($current['nullable'] != $expectedNullable) {
                                $diffs['nullable'] = [
                                    'current' => $current['nullable'],
                                    'expected' => $expectedNullable
                                ];
                            }
                        }

                        if (isset($colDef['unsigned']) && $current['unsigned'] !== null) {
                            if ($current['unsigned'] != $colDef['unsigned']) {
                                $diffs['unsigned'] = [
                                    'current' => $current['unsigned'],
                                    'expected' => $colDef['unsigned']
                                ];
                            }
                        }

                        if (isset($colDef['auto_increment']) && $current['auto_increment'] !== null) {
                            if ($current['auto_increment'] != $colDef['auto_increment']) {
                                $diffs['auto_increment'] = [
                                    'current' => $current['auto_increment'],
                                    'expected' => $colDef['auto_increment']
                                ];
                            }
                        }


                        if (!empty($diffs)) {
                            $result['differences'][] = [
                                'type' => 'column_mismatch',
                                'column' => $colName,
                                'differences' => $diffs
                            ];
                        }
                    }
                }

                // Check for extra columns in database
                foreach ($currentColumns as $colName => $colDef) {
                    if (!isset($schemaColumns[$colName])) {
                        $result['differences'][] = [
                            'type' => 'extra_column',
                            'column' => $colName,
                            'current' => $colDef
                        ];
                    }
                }

                // Add status
                $result['status'] = empty($result['differences']) ? 'OK' : 'DIFFERENT';
            } catch (\Exception $e) {
                $result['exists'] = false;
                $result['status'] = 'MISSING';
                $result['error'] = $e->getMessage();
            }

            $results[] = $result;
        }



        return $results;
    }
}
