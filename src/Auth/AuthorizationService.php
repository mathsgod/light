<?php

namespace Light\Auth;

use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;
use TheCodingMachine\GraphQLite\Security\AuthorizationServiceInterface;

class AuthorizationService implements AuthorizationServiceInterface
{
    protected $authenticationService;
    public function __construct(AuthenticationServiceInterface $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    public function isAllowed(string $right, $subject = null): bool
    {

        $user = $this->authenticationService->getUser();

        if ($user->user_id == 1) {
            return true;
        }



        return true;
    }
}
