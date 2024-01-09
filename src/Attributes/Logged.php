<?php

namespace Light\Attributes;

use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionAttribute;
use Psr\Http\Message\ResponseInterface;
use TheCodingMachine\GraphQLite\Annotations\Logged as AnnotationsLogged;

class Logged implements \PUXT\AttributeMiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler, ReflectionAttribute $attribute): ResponseInterface
    {

        if ($attribute->getName() == AnnotationsLogged::class) {
            $auth_service = new \Light\Auth\Service($request);
            if ($auth_service->isLogged() == false) {
                return new TextResponse("You are not logged in", 403);
            }
        }

        return $handler->handle($request);
    }
}
