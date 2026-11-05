<?php

/**
 * CI database setup script.
 * Run once before PHPUnit to create schema from db.json.
 * Env vars (DATABASE_*) must be set before calling this script.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$adapter = \Light\Db\Database::getDefault();

$converter = new \JsonToSql();
$sql = $converter->convertJsonToSql('db.json');
$adapter->connection()->exec($sql);
$adapter->clearMetadata();

echo "Database schema created successfully.\n";
