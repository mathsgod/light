<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);


use TheCodingMachine\GraphQLite\Annotations\InjectUser;

require_once __DIR__ . "/vendor/autoload.php";

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');



$app = new PUXT\App;
$app->pipe(new Light\App);
$app->addAttributeMiddleware(new Light\Attributes\Logged);
$app->addParameterHandler(InjectUser::class, new Light\ParameterHandlers\InjectedUser);

$app->run();
