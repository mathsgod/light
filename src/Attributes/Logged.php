<?php

namespace Light\Attributes;

use Attribute;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

#[Attribute()]
class Logged implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth_service = new \Light\Auth\Service($request);
        if ($auth_service->isLogged() == false) {
            return new TextResponse("You are not logged in", 403);
        }

        return $handler->handle($request);
    }
}
