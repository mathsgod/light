<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . "/vendor/autoload.php";

Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Expose-Headers: Set-Cookie');

//if reqest method is OPTIONS, return 200 OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$app = new Light\App();
if (class_exists(Light\OAuth2\ProviderFactory::class)) {
    Light\OAuth2\ProviderFactory::registerFromEnvironment($app);
} elseif (filter_var($_ENV['OAUTH_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    throw new RuntimeException('OAUTH_ENABLED requires mathsgod/light-oauth2 to be installed');
}
$app->run();
