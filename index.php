<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . "/vendor/autoload.php";

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

(new Light\App)->run();
