<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env if present (local dev); in CI, env vars are injected directly
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Initialise DB adapter (singleton) so all models can use it
\Light\Db\Adapter::Create();
