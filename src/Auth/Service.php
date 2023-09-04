<?php

namespace Light\Auth;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;
use Light\Model\User;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\GraphQLite\Security\AuthorizationServiceInterface;

class Service implements AuthenticationServiceInterface, AuthorizationServiceInterface
{
    protected $is_logged = false;
    protected $user = null;
    protected $app;
    public function __construct(ServerRequestInterface $request)
    {

        /** @var \Light\App $app */
        $this->app = $request->getAttribute(\Light\App::class);

        $cookies = $request->getCookieParams();
        if ($access_token = $cookies["access_token"]) {
            try {
                $payload = JWT::decode($access_token, new Key($_ENV["JWT_SECRET"], "HS256"));
                $this->user = User::Get($payload->id);
                $this->is_logged = true;
            } catch (Exception $e) {
                $this->is_logged = false;
            }
        }
    }

    public function isLogged(): bool
    {
        return $this->is_logged;
    }

    public function getUser(): ?object
    {
        return $this->user;
    }

    public function isAllowed(string $right, $subject = null): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        if ($user instanceof User) {
            $rbac = $this->app->getRbac();

            foreach ($user->getRoles() as $role) {
                if ($rbac->isGranted($role, $right, $subject)) {
                    return true;
                }
            }
        }

        return false;
    }
}
