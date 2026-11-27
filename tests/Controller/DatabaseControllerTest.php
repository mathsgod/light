<?php

namespace Light\Framework\Tests\Controller;

use Light\Framework\App;
use Light\Framework\Controller\DatabaseController;
use Light\Framework\Database\Sql;
use Light\Framework\Input\Table\Column;
use Light\Framework\Tests\TestCase;

final class DatabaseControllerTest extends TestCase
{
    public function testFixTableRepairsColumnsAndRecreatesMissingTable(): void
    {
        $app = new App();
        $controller = new DatabaseController();
        $connection = $app->getDatabase()->connection();
        $connection->exec('INSERT INTO `User` (`user_id`, `join_date`) VALUES (1, CURRENT_DATE)');
        $connection->exec('INSERT INTO `Notification` (`user_id`, `title`) VALUES (1, \'Keep me\')');
        $connection->exec('ALTER TABLE `Notification` DROP COLUMN `link`, MODIFY COLUMN `title` VARCHAR(40), '
            . 'MODIFY COLUMN `is_read` TINYINT NOT NULL DEFAULT 1, ADD COLUMN `extra` TEXT');

        self::assertTrue($controller->fixDatabaseTable($app, 'Notification'));
        $columns = array_column(Sql::columns('Notification'), null, 'name');
        self::assertSame(500, (int) $columns['link']['length']);
        self::assertSame(255, (int) $columns['title']['length']);
        self::assertStringContainsString('unsigned', $columns['is_read']['declaration']);
        self::assertSame('0', (string) $columns['is_read']['default']);
        self::assertArrayNotHasKey('extra', $columns);
        self::assertSame('Keep me', $connection->query('SELECT `title` FROM `Notification`')->fetchColumn());

        $connection->exec('DROP TABLE `Notification`');
        self::assertTrue($controller->fixDatabaseTable($app, 'Notification'));
        $columns = array_column(Sql::columns('Notification'), null, 'name');
        self::assertStringContainsString('auto_increment', $columns['notification_id']['extra']);
        $createStatement = $connection->query('SHOW CREATE TABLE `Notification`')->fetchColumn(1);
        self::assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `User` (`user_id`)', $createStatement);
    }

    public function testCreateAndRemoveTableWithSupportedColumnDefinitions(): void
    {
        $app = new App();
        $controller = new DatabaseController();
        $table = 'test created table';
        $fields = [];
        foreach ([
            ['id', 'int', null, false],
            ['display name', 'varchar', 40, true],
            ['code', 'varchar', null, false],
            ['notes', 'text', null, true],
            ['active', 'boolean', null, false],
            ['created', 'date', null, false],
        ] as [$name, $type, $length, $nullable]) {
            $field = new Column();
            $field->name = $name;
            $field->type = $type;
            $field->length = $length;
            $field->nullable = $nullable;
            $fields[] = $field;
        }

        try {
            self::assertTrue($controller->createDatabaseTable($app, $table, $fields));
            $columns = array_column(Sql::columns($table), null, 'name');
            self::assertSame(['id', 'display name', 'code', 'notes', 'active', 'created'], array_keys($columns));
            self::assertSame(['int', 'varchar', 'varchar', 'text', 'tinyint', 'date'], array_column($columns, 'type'));
            self::assertSame(40, (int) $columns['display name']['length']);
            self::assertSame(255, (int) $columns['code']['length']);
            self::assertSame([0, 1, 0, 1, 0, 0], array_map('intval', array_column($columns, 'nullable')));
            self::assertTrue($controller->removeDatabaseTable($app, $table));
            self::assertSame([], Sql::columns($table));
        } finally {
            $app->getDatabase()->connection()->exec('DROP TABLE IF EXISTS ' . Sql::identifier($table));
        }
    }

    public function testColumnMutationsPreserveRowsAndSupportExistingTypes(): void
    {
        $app = new App();
        $connection = $app->getDatabase()->connection();
        $controller = new DatabaseController();
        $table = 'test database columns';
        $tableName = Sql::identifier($table);
        $connection->exec("CREATE TABLE $tableName (id INT NOT NULL PRIMARY KEY)");

        try {
            self::assertTrue($controller->addDatabaseField($app, $table, 'quantity', 'INT', '', '42', true, false));
            self::assertTrue($controller->addDatabaseField($app, $table, 'label', 'VARCHAR', '32', "O'Reilly", false, false));
            self::assertTrue($controller->addDatabaseField($app, $table, 'price', 'DECIMAL', '8,2', '12.50', false, false));
            self::assertTrue($controller->addDatabaseField($app, $table, 'notes', 'TEXT', '', 'Default notes', false, false));
            self::assertTrue($controller->addDatabaseField($app, $table, 'score', 'FLOAT', '', '1.25', false, false));
            self::assertTrue($controller->addDatabaseField($app, $table, 'details', 'JSON', '', '{"ready":true}', false, false));
            $connection->exec("INSERT INTO $tableName (id) VALUES (1)");
            $row = $connection->query("SELECT * FROM $tableName WHERE id = 1")->fetch(\PDO::FETCH_ASSOC);
            self::assertSame(42, (int) $row['quantity']);
            self::assertSame("O'Reilly", $row['label']);
            self::assertSame('12.50', $row['price']);
            self::assertSame('Default notes', $row['notes']);
            self::assertSame(1.25, (float) $row['score']);
            self::assertSame(['ready' => true], json_decode($row['details'], true));
            $types = array_column(Sql::columns($table), 'type', 'name');
            self::assertSame(['text', 'float', 'json'], [$types['notes'], $types['score'], $types['details']]);
            self::assertTrue($controller->editDatabaseField($app, $table, 'notes', 'TEXT', '', 'Updated notes', true, false));
            self::assertTrue($controller->editDatabaseField($app, $table, 'score', 'FLOAT', '', '2.5', true, false));
            self::assertTrue($controller->editDatabaseField($app, $table, 'details', 'JSON', '', '{"ready":false}', true, false));

            $connection->exec("INSERT INTO $tableName (id, quantity) VALUES (2, NULL)");
            $row = $connection->query("SELECT * FROM $tableName WHERE id = 2")->fetch(\PDO::FETCH_ASSOC);
            self::assertSame('Updated notes', $row['notes']);
            self::assertSame(2.5, (float) $row['score']);
            self::assertSame(['ready' => false], json_decode($row['details'], true));
            self::assertTrue($controller->editDatabaseField($app, $table, 'quantity', 'INT', '', '7', false, false));
            self::assertSame([42, 0], array_map('intval', $connection->query("SELECT quantity FROM $tableName ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN)));
            $connection->exec("INSERT INTO $tableName (id) VALUES (3)");
            self::assertSame(7, (int) $connection->query("SELECT quantity FROM $tableName WHERE id = 3")->fetchColumn());

            self::assertTrue($controller->editDatabaseField($app, $table, 'id', 'INT', '', '', false, true));
            $connection->exec("INSERT INTO $tableName (label) VALUES ('next')");
            self::assertSame(4, (int) $connection->lastInsertId());

            self::assertTrue($controller->removeDatabaseFields($app, $table, ['label', 'price', 'notes', 'score', 'details']));
            self::assertSame(['id', 'quantity'], array_column(Sql::columns($table), 'name'));
            self::assertSame(4, (int) $connection->query("SELECT COUNT(*) FROM $tableName")->fetchColumn());
        } finally {
            $connection->exec("DROP TABLE $tableName");
        }
    }

    public function testEnumAndSetMembersAndDefaultsCanBeChanged(): void
    {
        $app = new App();
        $controller = new DatabaseController();
        $connection = $app->getDatabase()->connection();
        $table = 'test_column_members';
        $connection->exec("CREATE TABLE $table (id INT NOT NULL PRIMARY KEY)");

        try {
            self::assertTrue($controller->addDatabaseField($app, $table, 'state', 'ENUM', "'draft', 'owner''s review'", 'draft', true, false));
            self::assertTrue($controller->addDatabaseField($app, $table, 'flags', 'SET', "'urgent', 'reviewed'", 'urgent,reviewed', false, false));
            $connection->exec("INSERT INTO $table (id) VALUES (1)");
            $connection->exec("INSERT INTO $table (id, state) VALUES (2, NULL)");
            self::assertTrue($controller->editDatabaseField($app, $table, 'state', 'ENUM', "'draft', 'owner''s review', 'done'", "owner's review", false, false));
            self::assertTrue($controller->editDatabaseField($app, $table, 'flags', 'SET', "'urgent', 'reviewed', 'archived'", 'archived', false, false));
            $connection->exec("INSERT INTO $table (id) VALUES (3)");

            $rows = $connection->query("SELECT state, flags FROM $table ORDER BY id")->fetchAll(\PDO::FETCH_NUM);
            self::assertSame([
                ['draft', 'urgent,reviewed'],
                ["owner's review", 'urgent,reviewed'],
                ["owner's review", 'archived'],
            ], $rows);
            $types = array_column(Sql::columns($table), 'type', 'name');
            self::assertSame(['enum', 'set'], [$types['state'], $types['flags']]);
        } finally {
            $connection->exec("DROP TABLE $table");
        }
    }
}
