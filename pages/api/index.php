<?php
// GraphQL endpoint — dispatches any POST to the Light App's GraphQL executor.
// Route is registered by light-server via pages/api/index.php scanning.

use Laminas\Diactoros\Response\JsonResponse;
use Light\App;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return new class {

    public function Get(ServerRequestInterface $request): ResponseInterface
    {
        return new \Laminas\Diactoros\Response\TextResponse("Light API");
    }

    public function POST(ServerRequestInterface $request, App $app): ResponseInterface
    {
        $result = $app->execute($request);
        return new JsonResponse($result->toArray(), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function OPTIONS(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(null, 204);
    }

};
