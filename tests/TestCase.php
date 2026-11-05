<?php

namespace Light\Framework\Tests;

use Light\Db\Database;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // light-server scans <root>/pages via RecursiveDirectoryIterator
        // and throws if the directory is missing — ensure it exists.
        $pagesDir = getcwd() . "/pages";
        if (!is_dir($pagesDir)) {
            mkdir($pagesDir, 0777, true);
        }

        $pdo = Database::getDefault()->connection();
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'light_test') {
            throw new \LogicException('Framework integration tests require the disposable light_test database.');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
                $pdo->exec('TRUNCATE TABLE ' . \Light\Framework\Database\Sql::identifier($table));
            }
        } finally { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); }
    }

    protected function tearDown(): void
    {

        // Reset static container so the next test doesn't inherit a stale
        // Auth\Service pointing at rolled-back users
        \Light\Framework\Model::SetContainer(null);
        parent::tearDown();
    }
}
