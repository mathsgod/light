<?php

namespace Light\Auth;

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;
use Light\Model\User;
use Light\TokenExpiredException;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\GraphQLite\Exceptions\GraphQLException;
use TheCodingMachine\GraphQLite\Security\AuthorizationServiceInterface;

class Service implements AuthenticationServiceInterface, AuthorizationServiceInterface
{
    protected $is_logged = false;
    protected $user = null;
    protected $org_user = null;
    protected $app;
    protected $view_as = false;
    protected $token = null;
    protected $jti = null;
    protected $token_status = 'valid';


    public function __construct(ServerRequestInterface $request)
    {

        /** @var \Light\App $app */
        $this->app = $request->getAttribute(\Light\App::class);

        $cookies = $request->getCookieParams();

        //get Bearer token from Authorization header
        if ($authHeader = $request->getHeaderLine("Authorization")) {
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        $token = $token ?? $cookies["access_token"] ?? null;

        if (!$token) {
            return;
        }
        $this->token = $token;

        try {
            $payload = JWT::decode($this->token, new Key($_ENV["JWT_SECRET"], "HS256"));
            if ($payload->type == "access_token") {

                //decode user

                $this->jti = $payload->jti;

                if ($payload->view_as) {
                    $this->view_as = true;
                    $this->user = User::Get($payload->view_as);
                    $this->org_user = User::Get($payload->id);
                } else {
                    $this->user = User::Get($payload->id);

                    $this->user->saveLastAccessTime($this->jti);
                }
                $this->is_logged = true;
            }
        } catch (ExpiredException $e) {
            $this->token_status = 'expired';
        } catch (Exception $e) {
            $this->is_logged = false;
        }
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getJti()
    {
        return $this->jti;
    }

    public function isViewAsMode(): bool
    {
        return $this->view_as;
    }

    public function isLogged(): bool
    {

        if ($this->token_status == 'expired') {
            throw new TokenExpiredException('TOKEN_EXPIRED');
        }

        return $this->is_logged;
    }

    public function getUser(): ?object
    {
        if ($this->token_status == 'expired') {
            throw new TokenExpiredException('TOKEN_EXPIRED');
        }
        return $this->user;
    }

    public function getOrginalUser(): ?object
    {
        if ($this->org_user)
            return $this->org_user;
        return $this->user;
    }

    public function isAllowed(string $right, $subject = null): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }


        if ($user instanceof User) {

            if ($user->is("Administrators")) {
                return true;
            }

            $rbac = $this->app->getRbac();

            foreach ($user->getRoles() as $role) {
                if ($rbac->hasRole($role) && $rbac->getRole($role)->can($right)) {
                    return true;
                }
            }
        }

        return false;
    }
}
