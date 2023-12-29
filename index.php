<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

use GraphQL\Error\DebugFlag;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use League\Container\ReflectionContainer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

require_once __DIR__ . "/vendor/autoload.php";
try {
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
} catch (Exception $e) {
    echo $e->getMessage();
    die();
}




$request = ServerRequestFactory::fromGlobals();
if (!$request->getParsedBody()) {
    $body = json_decode(file_get_contents('php://input'), true);
    $request = $request->withParsedBody($body);
}


$app = new Light\App;

class RequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {

        /** @var Light\App $app */
        $app = $request->getAttribute(Light\App::class);

        try {
            \Light\Model::GetSchema()->setContainer($app->getContainer());
        } catch (Exception $e) {
            //db may not be ready yet
        }

        $container=$app->getContainer();
        $container->add(ServerRequestInterface::class, $request);
        


       // $factory = $app->getSchemaFactory();
       /*  $as = new Light\Auth\Service($request);
        $factory->setAuthenticationService($as);
        $factory->setAuthorizationService($as);
         */
        $result = $app->execute($request);

        try {

            return new JsonResponse($result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE));
        } catch (\Exception $e) {
            return new JsonResponse(['errors' => [
                ["message" => $e->getMessage()]
            ]]);
        }
    }
}

$response = $app->process($request, new RequestHandler);

// Send the response to the HTTP client
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);
