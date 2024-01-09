<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

use GraphQL\Error\DebugFlag;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use League\Container\ReflectionContainer;
use Light\Model\Config;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

require_once __DIR__ . "/vendor/autoload.php";

$app = new PUXT\App;
$app->addAttributeMiddleware(new Light\Attributes\Logged);
$app->addParameterHandler(InjectUser::class, new Light\ParameterHandlers\InjectedUser);

$app->pipe(new Light\App);
$app->run();
