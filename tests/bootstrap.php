<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env if present (local dev); in CI, env vars are injected directly
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Initialise the default PDO-backed database for all models
\Light\Db\Database::getDefault();

// Composer changes can remove optional GraphQL types cached by earlier test runs.
(new \Symfony\Component\Cache\Adapter\FilesystemAdapter('light'))->clear();
