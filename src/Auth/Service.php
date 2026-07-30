<?php

namespace Light\Auth;

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;
use Light\Model\APIKey;
use Light\Model\User;
use Light\TokenExpiredException;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\GraphQLite\Exceptions\GraphQLException;
use TheCodingMachine\GraphQLite\Security\AuthorizationServiceInterface;

class Service implements AuthenticationServiceInterface, AuthorizationServiceInterface
{
    public const REVOKED_SESSION_PREFIX = "revoked_session_";

    protected bool $is_logged = false;
    protected ?User $user = null;
    protected ?User $org_user = null;
    protected \Light\App $app;
    protected bool $view_as = false;
    protected bool $is_api_key = false;
    protected ?string $token = null;
    protected ?string $jti = null;
    protected ?string $session_id = null;
    protected string $token_status = 'valid';


    public function __construct(ServerRequestInterface $request)
    {

        /** @var \Light\App $app */
        $this->app = $request->getAttribute(\Light\App::class);
        $cache = $this->app->getCache();

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
                $this->is_api_key = !empty($payload->name);

                // Browser sessions keep a stable sid while access-token jtis
                // rotate. Legacy browser tokens fall back to their jti.
                if (!$this->is_api_key) {
                    $this->session_id = !empty($payload->sid)
                        ? (string) $payload->sid
                        : $this->jti;
                }

                if ($cache->has("revoked_token_" . $this->jti)) {
                    return;
                }

                if (
                    $this->session_id
                    && $cache->has(self::REVOKED_SESSION_PREFIX . $this->session_id)
                ) {
                    return;
                }

                // A refresh-token reuse lock applies to interactive browser
                // sessions only. API keys have their own database-backed
                // revocation lifecycle and must remain independently usable.
                if (
                    !$this->is_api_key
                    && !empty($payload->id)
                    && $cache->has("user_sessions_revoked_" . $payload->id)
                ) {
                    return;
                }

                // If token has a name field, it's an API key — verify the record still exists
                if ($this->is_api_key) {
                    if (!APIKey::Get(["key" => $this->token])) {
                        return;
                    }
                }



                if ($payload->view_as) {
                    $this->view_as = true;
                    $this->user = User::Get($payload->view_as);
                    $this->org_user = User::Get($payload->id);
                } else {
                    $this->user = User::Get($payload->id);

                    // API keys are not interactive browser sessions and do not
                    // have a UserLog session row to update.
                    if (!$this->is_api_key && $this->session_id) {
                        $this->user->saveLastAccessTime($this->session_id);
                    }
                }
                $this->is_logged = true;
            }
        } catch (ExpiredException $e) {
            $this->token_status = 'expired';
        } catch (Exception $e) {
            $this->is_logged = false;
        }
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getJti(): ?string
    {
        return $this->jti;
    }

    public function getSessionId(): ?string
    {
        return $this->session_id;
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

    public function getUser(): ?User
    {
        if ($this->token_status == 'expired') {
            throw new TokenExpiredException('TOKEN_EXPIRED');
        }
        return $this->user;
    }

    public function getOrginalUser(): ?User
    {
        if ($this->org_user)
            return $this->org_user;
        return $this->user;
    }

    public function isAllowed(string $right, mixed $subject = null): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        if ($user instanceof User) {
            $rbac = $this->app->getRbac();
            $u = $rbac->getUser($user->user_id);
            return $u?->can($right) ?? false;
        }

        return false;
    }
}
