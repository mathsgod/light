<?php

namespace Light\Auth;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Light\Model\Config;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;
use Light\Model\User;
use Light\Model\UserLog;
use Psr\Http\Message\ServerRequestInterface;
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

        $this->token = $token ?? $cookies["access_token"] ?? null;

        if ($this->token) {

            try {
                $payload = JWT::decode($this->token, new Key($_ENV["JWT_SECRET"], "HS256"));
                if ($payload->type != "access_token") {
                    return;
                }


                $this->jti = $payload->jti;
/*                 if ($payload->jti) {
                    if ($cache->has("logout_" . $payload->jti)) {
                        return;
                    }

                    //get last Login jti
                    if (Config::Value("concurrent_login_disabled", false)) {
                        $ul = UserLog::Query(["result" => "SUCCESS", "user_id" => $payload->id])
                            ->sort("login_dt:desc")->first();
                        if ($ul) {
                            if ($ul->jti != $this->jti) {
                                $cache->set("logout_" . $this->jti, true, 60 * 60 * 24);
                                return;
                            }
                        }
                    }
                }
 */
          

                if ($payload->view_as) {
                    $this->view_as = true;
                    $this->user = User::Get($payload->view_as);
                    $this->org_user = User::Get($payload->id);
                } else {
                    $this->user = User::Get($payload->id);

                    $this->user->saveLastAccessTime($this->jti);
                    
                }
                $this->is_logged = true;
            } catch (Exception $e) {
                $this->is_logged = false;
            }
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
        return $this->is_logged;
    }

    public function getUser(): ?object
    {
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
