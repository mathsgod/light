<?php

namespace Light\Framework\Database;

use Light\Db\Database;
use PDO;

/** PDO helpers for framework schema administration and audit writes. */
final class Sql
{
    public static function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public static function rows(string $sql, array $parameters = []): array
    {
        $statement = Database::getDefault()->connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function execute(string $sql, array $parameters = []): int
    {
        $statement = Database::getDefault()->connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
    }

    public static function insert(string $table, array $values, ?PDO $connection = null): void
    {
        $columns = implode(', ', array_map(self::identifier(...), array_keys($values)));
        $statement = ($connection ?? Database::getDefault()->connection())->prepare('INSERT INTO ' . self::identifier($table) . " ($columns) VALUES (" . implode(', ', array_fill(0, count($values), '?')) . ')');
        $statement->execute(array_values($values));
    }

    public static function columns(string $table): array
    {
        return self::rows('SELECT COLUMN_NAME AS name, DATA_TYPE AS type, COLUMN_TYPE AS declaration, COLUMN_DEFAULT AS `default`, IS_NULLABLE = \'YES\' AS nullable, CHARACTER_MAXIMUM_LENGTH AS length, NUMERIC_PRECISION AS precision_length, COLUMN_KEY = \'PRI\' AS `primary`, EXTRA LIKE \'%auto_increment%\' AS auto_increment, COLUMN_TYPE LIKE \'%unsigned%\' AS `unsigned`, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$table]);
    }
}
