<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);


use TheCodingMachine\GraphQLite\Annotations\InjectUser;

require_once __DIR__ . "/vendor/autoload.php";

$app = new PUXT\App;
$app->pipe(new Light\App);
$app->addAttributeMiddleware(new Light\Attributes\Logged);
$app->addParameterHandler(InjectUser::class, new Light\ParameterHandlers\InjectedUser);

$app->run();
