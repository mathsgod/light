<?php

namespace Light\ParameterHandlers;

class InjectedUser implements \PUXT\ParameterHandlerInterface
{
    public function handle(\Psr\Http\Message\ServerRequestInterface $request, \ReflectionAttribute $attribute, \ReflectionParameter $parameter)
    {

        $auth_service = new \Light\Auth\Service($request);
        if ($auth_service->isLogged() == false) {
            return null;
        }
        return $auth_service->getUser();
    }
}
