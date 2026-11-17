<?php

namespace Light\Framework\Controller;

use GraphQL\Error\Error;
use Light\Framework\App;
use Light\Framework\Model\User;
use Light\Framework\Type\WebAuthn;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;



class WebAuthnController
{
    #[Mutation]
    #[Logged]
    public function deleteWebAuthn(#[InjectUser] User $user, string $uuid, #[Autowire] App $app): bool
    {
        return $app->getAuthentication()->deleteCredential($user, $uuid);
    }

    #[Query]
    #[Logged]
    /**
     * @return \Light\Framework\Type\WebAuthn[]
     * @deprecated use my{ webAuthn } instead
     */
    public function listWebAuthn(#[InjectUser] User $user): array
    {
        $data = [];
        foreach ($user->credential as $credential) {
            $data[] = new WebAuthn($credential);
        }
        return $data;
    }


    #[Mutation]
    /** @param mixed $assertion */
    public function webAuthnAssertion(?string $username, $assertion, #[Autowire] App $app, #[Autowire] ServerRequestInterface $request): bool
    {
        if (!is_array($assertion)) throw new Error("Invalid credential");
        return $app->getAuthentication($request)->completeAssertion($assertion);
    }

    #[Mutation]
    #[Logged]
    /** @param mixed $registration */
    public function webAuthnRegister(#[InjectUser] User $user, #[Autowire] App $app, $registration, #[Autowire] ServerRequestInterface $request): bool
    {
        if (!is_array($registration)) throw new Error("Invalid credential");
        return $app->getAuthentication($request)->completeRegistration($user, $registration);
    }
}
