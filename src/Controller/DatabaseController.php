<?php

namespace Light\Framework\Controller;

use Exception;
use Firebase\JWT\JWT;
use Google\Service\MigrationCenterAPI\UploadFileInfo;
use GraphQL\Error\Error;
use JsonToSql;
use Light\Entity\DatabaseFacade;
use Light\Entity\Migrations\MigrationBuilder;
use Light\Entity\Migrations\TableBuilder;
use Light\Framework\App;
use Light\Framework\Database\Dump;
use Light\Framework\Database\Sql;
use Light\Framework\Type\System;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use MysqlSchemaMigrate\Importer;
use Psr\Http\Message\UploadedFileInterface;

class DatabaseController
{

    #[Mutation]
    #[Right("system.database.schema_import")]
    public function importDatabaseSchema(UploadedFileInterface $schema): bool
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
            allowDrop: false,
        );

        \Light\Db\Database::getDefault()->clearMetadata();
        return true;
    }


    /*    #[Mutation]
    #[Right("system.database.event:alter")]
    public function alterDatabaseEvent(#[Autowire] App $app, string $name, string $body): bool
    {
        $db = $app->getDatabase();
        try {
            $db->connection()->exec("ALTER EVENT $name DO $body");
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        \Light\Db\Database::getDefault()->clearMetadata();
        return true;
    }
 */

    #[Mutation(name: "lightDatabaseTruncateTable")]
    #[Right("system.database.table.truncate")]
    public function truncateDatabaseTable(#[Autowire] App $app, string $table): bool
    {
        $db = $app->getDatabase();
        try {
            $db->connection()->exec("TRUNCATE TABLE " . \Light\Framework\Database\Sql::identifier($table));
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        \Light\Db\Database::getDefault()->clearMetadata();
        return true;
    }

    #[Mutation(name: "lightDatabaseRemoveTable")]
    #[Right("system.database.table.remove")]
    public function removeDatabaseTable(#[Autowire] App $app, string $table): bool
    {
        $db = $app->getDatabase();
        try {
            $migration = (new MigrationBuilder())->dropTable($table);
            (new DatabaseFacade($db->connection()))->executeMigration($migration);
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        $db->clearMetadata();
        return true;
    }


    #[Mutation(name: "lightDatabaseAddField")]
    #[Right("system.database.field.add")]
    public function addDatabaseField(#[Autowire] App $app, string $table, string $field, string $type, string $length, string $default, bool $nullable, bool $autoincrement): bool
    {
        return $this->changeDatabaseField($app, $table, $field, $type, $length, $default, $nullable, $autoincrement, false);
    }

    #[Mutation(name: "lightDatabaseEditField")]
    #[Right("system.database.field.add")]
    public function editDatabaseField(#[Autowire] App $app, string $table, string $field, string $type, string $length, string $default, bool $nullable, bool $autoincrement): bool
    {
        return $this->changeDatabaseField($app, $table, $field, $type, $length, $default, $nullable, $autoincrement, true);
    }

    private function changeDatabaseField(App $app, string $table, string $field, string $type, string $length, string $default, bool $nullable, bool $autoincrement, bool $edit): bool
    {
        $db = $app->getDatabase();
        $connection = $db->connection();
        $facade = new DatabaseFacade($connection);
        $tableName = Sql::identifier($table);
        $fieldName = Sql::identifier($field);
        $normalizedType = strtolower(trim($type));
        $baseType = explode(' ', $normalizedType, 2)[0];
        $numericType = in_array($baseType, [
            'int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float', 'double',
            'real', 'decimal', 'dec', 'numeric', 'fixed', 'bool', 'boolean', 'bit',
        ], true);
        $defaultValue = $default === '' ? null : $default;
        $requiresQuotedNumericDefault = false;
        if ($numericType && $defaultValue !== null) {
            $integer = filter_var($defaultValue, FILTER_VALIDATE_INT);
            if ($integer !== false) {
                $defaultValue = $integer;
            } elseif (is_numeric($defaultValue) && is_finite((float) $defaultValue)
                && strlen(preg_replace('/[^0-9]/', '', $defaultValue)) <= 15) {
                $defaultValue = (float) $defaultValue;
            } else {
                // Keep exact numeric literals that cannot safely pass through a PHP float.
                $requiresQuotedNumericDefault = true;
            }
        }

        try {
            $typeSql = $this->columnTypeSql($normalizedType, $baseType, $length, $autoincrement);
            $migration = null;
            $members = [];
            // The builder requires an inline primary key for AUTO_INCREMENT.
            if (!$autoincrement && !$requiresQuotedNumericDefault) {
                $members = in_array($normalizedType, ['enum', 'set'], true) ? $this->parseColumnMembers($length) : [];
                $migration = new MigrationBuilder();
                $configure = static function (TableBuilder $builder) use ($field, $normalizedType, $members, $typeSql, $nullable, $defaultValue): void {
                    $column = match ($normalizedType) {
                        'enum' => $builder->enum($field, $members),
                        'set' => $builder->set($field, $members),
                        default => $builder->text($field)->columnType($typeSql),
                    };
                    $column->nullable($nullable);
                    if ($defaultValue !== null || $nullable) {
                        $column->defaultValue($defaultValue);
                    }
                };
                if ($edit) {
                    $migration->alterColumn($table, $configure);
                } else {
                    $migration->addColumn($table, $configure);
                }
            }

            if ($edit && !$nullable) {
                $fillValue = in_array($normalizedType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float', 'double', 'decimal'], true) ? 0 : '';
                if ($members !== [] && $normalizedType === 'enum') {
                    $fillValue = $defaultValue ?? $members[0];
                }
                $facade->executeSql("UPDATE $tableName SET $fieldName = ? WHERE $fieldName IS NULL", [$fillValue]);
            }

            if ($migration !== null) {
                $facade->executeMigration($migration);
            } else {
                // Keep MySQL column definitions that light-entity's builder cannot represent.
                $defaultSql = $default !== '' ? 'DEFAULT ' . $connection->quote($default) : ($nullable ? 'DEFAULT NULL' : '');
                $nullSql = $nullable ? 'NULL' : 'NOT NULL';
                $autoSql = $autoincrement ? 'AUTO_INCREMENT' : '';
                $operation = $edit ? 'MODIFY' : 'ADD';
                $connection->exec("ALTER TABLE $tableName $operation $fieldName $typeSql $nullSql $defaultSql $autoSql");
            }
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }

        $db->clearMetadata();
        return true;
    }

    private function columnTypeSql(string $type, string $baseType, string $length, bool $autoincrement): string
    {
        $integers = ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'];
        $decimals = ['decimal', 'dec', 'numeric', 'fixed', 'float', 'double', 'real', 'double precision'];
        $singleArgument = ['bit', 'char', 'varchar', 'binary', 'varbinary', 'time', 'datetime', 'timestamp', 'year', 'text', 'blob'];
        $noArguments = ['bool', 'boolean', 'date', 'json', 'tinytext', 'mediumtext', 'longtext', 'tinyblob', 'mediumblob', 'longblob', 'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection', 'enum', 'set'];
        $unsigned = str_ends_with($type, ' unsigned');
        $name = $unsigned ? substr($type, 0, -9) : $type;
        $isInteger = in_array($name, $integers, true);
        $isDecimal = in_array($name, $decimals, true);
        $allowsOneArgument = $isInteger || in_array($name, $singleArgument, true);
        if ((!$allowsOneArgument && !$isDecimal && !in_array($name, $noArguments, true))
            || ($unsigned && !$isInteger && !$isDecimal)
            || ($autoincrement && !$isInteger)) {
            throw new \InvalidArgumentException('Unsupported MySQL column type or modifier.');
        }

        // Member lists are handled by the ENUM and SET builders.
        if (in_array($baseType, ['enum', 'set'], true)) {
            return strtoupper($name);
        }

        $length = trim($length);
        if ($length !== '' && (!$allowsOneArgument && !$isDecimal
            || !preg_match($isDecimal ? '/\A[0-9]+(?: *, *[0-9]+)?\z/' : '/\A[0-9]+\z/', $length))) {
            throw new \InvalidArgumentException('Invalid MySQL column type arguments.');
        }
        $name = $name === 'double precision' ? 'double' : $name;
        return strtoupper($name) . ($length === '' ? '' : '(' . $length . ')') . ($unsigned ? ' UNSIGNED' : '');
    }

    /** @return list<string> */
    private function parseColumnMembers(string $length): array
    {
        $pattern = <<<'REGEX'
~\G\s*(?:'((?:[^'\\]|'')*)'|"((?:[^"\\]|"")*)")\s*(,|\z)~
REGEX;
        $members = [];
        $offset = 0;
        do {
            if (!preg_match($pattern, $length, $match, PREG_UNMATCHED_AS_NULL, $offset)) {
                throw new \InvalidArgumentException('Expected a comma-separated list of quoted ENUM or SET members.');
            }
            $members[] = $match[1] !== null
                ? str_replace("''", "'", $match[1])
                : str_replace('""', '"', $match[2]);
            $offset += strlen($match[0]);
        } while ($match[3] === ',');

        return $members;
    }

    #[Mutation(name: "lightDatabaseRemoveFields")]
    #[Right("system.database.field.remove")]
    /**
     * @param string[] $fields
     */
    public function removeDatabaseFields(#[Autowire] App $app, string $table, array $fields): bool
    {
        $db = $app->getDatabase();
        $migration = new MigrationBuilder();
        foreach ($fields as $field) {
            $migration->dropColumn($table, $field);
        }
        try {
            (new DatabaseFacade($db->connection()))->executeMigration($migration);
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        } finally {
            $db->clearMetadata();
        }
        return true;
    }

    #[Mutation(name: "lightDatabaseCreateTable")]
    #[Right("system.database.table.create")]
    #[Logged]
    /**
     * @param \Light\Framework\Input\Table\Column[] $fields
     */
    public function createDatabaseTable(#[Autowire] App $app, string $name, array $fields): bool
    {
        $db = $app->getDatabase();

        if ($fields === []) {
            throw new \InvalidArgumentException('A table requires columns.');
        }

        $migration = new MigrationBuilder();
        $migration->createTable($name, static function (TableBuilder $table) use ($fields): void {
            foreach ($fields as $column) {
                $definition = match ($column->type) {
                    'int' => $table->integer($column->name),
                    'varchar' => $table->string($column->name, max(1, $column->length ?? 255)),
                    'text' => $table->text($column->name),
                    'boolean' => $table->boolean($column->name),
                    'date' => $table->date($column->name),
                    default => throw new \InvalidArgumentException('Unsupported column type: ' . $column->type),
                };
                $definition->nullable($column->nullable ?? false);
            }
        });

        try {
            (new DatabaseFacade($db->connection()))->executeMigration($migration);
        } catch (Exception $e) {
            throw new Error($e->getMessage());
        }
        $db->clearMetadata();
        return true;
    }

    #[Mutation]
    #[Right("system.database.restore")]
    public function restoreDatabase(#[Autowire] App $app, UploadedFileInterface $file): bool
    {
        Dump::import($app->getDatabase(), $file->getStream());
        return true;
    }

    #[Mutation]
    #[Right("system.database.fix")]
    public function fixDatabaseTable(#[Autowire] App $app, string $name): bool
    {
        $db = $app->getDatabase();

        $schema = new \Light\Framework\Database\Schema();

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
                $db->connection()->exec($sql);
            } catch (Exception $e) {
                throw new Error("Failed to create table '$name': " . $e->getMessage());
            }
            \Light\Db\Database::getDefault()->clearMetadata();
        return true;
        }

        $tables = array_column(json_decode(file_get_contents(__DIR__ . '/../../db.json'), true), null, 'name');
        $expectedColumns = array_column($tables[$name]['columns'], null, 'name');
        $connection = $db->connection();
        $facade = new DatabaseFacade($connection);
        try {
            foreach ($tableResult['differences'] as $diff) {
                $column = $diff['column'];
                $migration = new MigrationBuilder();
                if ($diff['type'] === 'extra_column') {
                    $migration->dropColumn($name, $column);
                } else {
                    $expected = $expectedColumns[$column];
                    $type = strtoupper($expected['type']);
                    if (!empty($expected['length'])) {
                        $type .= '(' . $expected['length'] . ')';
                    }
                    if (!empty($expected['unsigned'])) {
                        $type .= ' UNSIGNED';
                    }
                    $nullable = $expected['nullable'] ?? true;
                    $default = $expected['default'] ?? null;
                    $add = $diff['type'] === 'missing_column';

                    if (!empty($expected['auto_increment'])) {
                        // Keep the existing key without redeclaring it as a primary key.
                        $operation = $add ? 'ADD COLUMN' : 'MODIFY COLUMN';
                        $nullSql = $nullable ? 'NULL' : 'NOT NULL';
                        $connection->exec('ALTER TABLE ' . Sql::identifier($name) . " $operation "
                            . Sql::identifier($column) . " $type $nullSql AUTO_INCREMENT");
                        continue;
                    }

                    if (is_string($default) && is_numeric($default)
                        && in_array(strtolower($expected['type']), ['int', 'tinyint'], true)) {
                        $default = (int) $default;
                    }
                    $configure = static function (TableBuilder $table) use ($column, $type, $nullable, $default): void {
                        $definition = $table->text($column)->columnType($type)->nullable($nullable);
                        if ($default !== null) {
                            $definition->defaultValue($default);
                        }
                    };
                    if ($add) {
                        $migration->addColumn($name, $configure);
                    } else {
                        $migration->alterColumn($name, $configure);
                    }
                }
                $facade->executeMigration($migration);
            }
        } catch (Exception $e) {
            throw new Error("Failed fix column '$column': {$e->getMessage()}");
        } finally {
            $db->clearMetadata();
        }

        return true;
    }
}
