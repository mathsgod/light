<?php

namespace Light\Auth;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;
use Light\Model\User;
use Psr\Http\Message\ServerRequestInterface;

class AuthenticationService implements AuthenticationServiceInterface
{
    protected $is_logged = false;
    protected $user = null;
    public function __construct(ServerRequestInterface $request)
    {
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
}
