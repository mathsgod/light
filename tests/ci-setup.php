<?php

/**
 * CI database setup script.
 * Run once before PHPUnit to create schema from db.json.
 * Env vars (DATABASE_*) must be set before calling this script.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$adapter = \Light\Db\Adapter::Create();

$converter = new \JsonToSql();
$sql = $converter->convertJsonToSql('db.json');
$adapter->getDriver()->getConnection()->execute($sql);

echo "Database schema created successfully.\n";
