<?php

namespace Light;

use Exception;
use Firebase\JWT\JWT;
use GQL\Type\MixedTypeMapperFactory;
use GraphQL\GraphQL;
use GraphQL\Upload\UploadMiddleware;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Permissions\Rbac\Rbac;
use Laminas\Permissions\Rbac\RoleInterface;
use Light\Model\Config;
use Light\Model\Permission;
use Light\Model\Role;
use Light\Model\User;
use Light\Model\UserLog;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use R\DB\Schema;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\GraphQLite\SchemaFactory;

class App implements MiddlewareInterface
{
    protected $container;
    protected $factory;

    protected $rbac;
    protected $permissions = [];

    protected $dev_mode = true;

    protected $cache;

    protected $menus = [];

    public function __construct()
    {
        $this->container = new \League\Container\Container();
        $this->cache = new Psr16Cache(new FilesystemAdapter());
        $this->factory = new SchemaFactory($this->cache, $this->container);

        $this->factory->addControllerNamespace("\\Light\\Controller\\");
        $this->factory->addTypeNamespace("\\Light\\Model\\");
        $this->factory->addTypeNamespace("\\Light\\Input\\");
        $this->factory->addTypeNamespace("\\Light\\Type\\");

        $this->container->add(App::class, $this);
        $this->container->add(Controller\AppController::class);
        $this->container->add(Controller\SystemController::class);
        $this->container->add(Controller\AuthController::class);
        $this->container->add(Controller\UserController::class);
        $this->container->add(Controller\RoleController::class);
        $this->container->add(Controller\EventLogController::class);
        $this->container->add(Controller\UserRoleController::class);
        $this->container->add(Controller\PermissionController::class);
        $this->container->add(Controller\ConfigController::class);
        $this->container->add(Controller\UserLogController::class);
        $this->container->add(Controller\MailLogController::class);
        $this->container->add(Controller\FileSystemController::class);
        $this->container->add(Controller\TranslateController::class);
        $this->container->add(Controller\WebAuthnController::class);


        $this->factory->addRootTypeMapperFactory(new MixedTypeMapperFactory);
        $this->factory->addTypeMapperFactory(new \R\DB\GraphQLite\Mappers\TypeMapperFactory);


        $this->rbac = new Rbac();
        $this->loadRbac();

        try {
            if ($config = Config::Get(["name" => "dev_mode"])) {
                $this->dev_mode = (bool)$config->value;
            }
        } catch (Exception $e) {
        }

        $this->loadMenu();
    }

    private function loadMenu()
    {
        $this->addMenus(Yaml::parseFile(dirname(__DIR__) . '/menus.yml')); //system default

        $this->addMenus($this->getCustomMenus());

        //if file manager is enabled, add to menus
        if ($this->isFileManagerEnabled()) {
            $this->addMenus([
                [
                    "label" => "File Manager",
                    "to" => "/FileManager",
                    "icon" => "sym_o_folder",
                    "permission" => "fs"
                ]
            ]);
        }
    }

    public function getMenus()
    {
        return $this->menus;
    }

    public function addMenus(array $menus)
    {
        foreach ($menus as $m) {
            $this->menus[] = $m;
        }
    }

    public function getDatabase()
    {
        return Schema::Create();
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function getMailer()
    {
        $mailer = new Mailer();
        $mailer->isSMTP();
        $mailer->SMTPAuth = true;




        return $mailer;
    }

    /**
     * Adds permissions to a given role.
     *
     * @param RoleInterface|string $role The role to add permissions to.
     * @param string[] $permissions An array of permissions to add to the role.
     * @return void
     */
    public function addRolePermissions(RoleInterface|string $role, array $permissions)
    {
        if (!$this->rbac->hasRole($role)) {
            $this->rbac->addRole($role);
        }

        $r = $this->rbac->getRole($role);
        foreach ($permissions as $p) {
            $r->addPermission($p);
            $this->permissions[] = $p;
        }

        //unique
        $this->permissions = array_unique($this->permissions);
    }

    public function loadRbac()
    {
        $this->rbac->setCreateMissingRoles(true);


        /** Roles */
        $this->rbac->addRole("Administrators");
        $this->rbac->getRole("Administrators")->addPermission("#administrators");

        $this->rbac->addRole("Power Users", ["Administrators"]);
        $this->rbac->getRole("Power Users")->addPermission("#power users");

        $this->rbac->addRole("Users", ["Power Users"]);
        $this->rbac->getRole("Users")->addPermission("#users");

        $this->rbac->addRole("Everyone", ["Users"]);
        $this->rbac->getRole("Everyone")->addPermission("#everyone");

        //check if table exists


        try {


            foreach (Role::Query() as $q) {

                if (!$this->rbac->hasRole($q->name)) {
                    $this->rbac->addRole($q->name);
                    $this->rbac->getRole($q->name)->addPermission("#" . strtolower($q->name));
                }

                if (!$this->rbac->hasRole($q->child)) {
                    $this->rbac->addRole($q->child);
                    $this->rbac->getRole($q->child)->addPermission("#" . strtolower($q->child));
                }

                $this->rbac->getRole($q->name)->addChild($this->rbac->getRole($q->child));
            }
        } catch (Exception $e) {
            // may be mysql not ready

        }
        /** Permissions */
        $all = Yaml::parseFile(dirname(__DIR__) . '/permissions.yml');

        foreach ($all as $role => $permissions) {
            $this->addRolePermissions($role, $permissions);
        }

        foreach (Permission::Query() as $p) {

            $this->rbac->getRole($p->role)->addPermission($p->value);
        }



        //unique
        $this->permissions = array_unique($this->permissions);


        $this->container->add(Rbac::class, $this->rbac);
    }

    private function getMenusPermission(array $menus)
    {
        $p = [];
        foreach ($menus as $m) {
            if ($m["permission"]) {
                $p[] = $m["permission"];
            }

            if ($m["children"]) {
                $p = array_merge($p, $this->getMenusPermission($m["children"]));
            }
        }

        return $p;
    }

    public function getPermissions()
    {
        $permissions = $this->permissions;
        //permissions from menus

        foreach ($this->getMenusPermission($this->menus) as $p) {
            $permissions[] = $p;
        }

        //permissions from db

        try {
            foreach (Permission::Query() as $p) {
                $permissions[] = $p->value;
            }
        } catch (Exception $e) {
        }

        $permissions = array_unique($permissions);
        //sort
        sort($permissions);
        return  $permissions;
    }


    public function isTwoFactorAuthentication(): bool
    {
        if (!$config = Config::Get(["name" => "two_factor_authentication"])) {
            return false;
        }
        return $config->value;
    }




    public function getRbac()
    {
        return $this->rbac;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getSchemaFactory()
    {
        return $this->factory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() == "OPTIONS") {
            return new EmptyResponse(200);
        }

        $uploadMiddleware = new UploadMiddleware();
        $request = $uploadMiddleware->processRequest($request);

        $request = $request->withAttribute(self::class, $this);

        $auth_service = new Auth\Service($request);

        $this->factory->setAuthenticationService($auth_service);
        $this->factory->setAuthorizationService($auth_service);


        if (!$this->isDevMode()) {
            $this->factory->prodMode();
        }

        $this->container->add(ServerRequestInterface::class, $request);
        $this->container->add(Auth\Service::class, $auth_service);

        return $handler->handle($request);
    }

    public function execute(ServerRequestInterface $request)
    {
        $body = $request->getParsedBody();
        $query = $body["query"];
        $variableValues = $body["variables"] ?? null;

        $schema = $this->factory->createSchema();
        return  GraphQL::executeQuery($schema, $query, null, null, $variableValues);
    }

    public function isDevMode(): bool
    {
        return $this->dev_mode;
    }

    public function getCustomMenus(): array
    {
        if (!$menus = Config::Get(["name" => "menus"])) {
            return [];
        }
        return json_decode($menus->value, true) ?? [];
    }

    public function isFileManagerEnabled(): bool
    {
        if (!$config = Config::Get(["name" => "file_manager"])) {
            return false;
        }
        return $config->value ?? false;
    }

    public function userLogin(User $user)
    {
        $payload = [
            "iss" => "light server",
            "jti" => uniqid(),
            "iat" => time(),
            "exp" => time() + 3600 * 8,
            "role" => "Users",
            "id" => $user->user_id,
            "type" => "access_token"
        ];

        $token = JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");

        //save UserLog
        UserLog::Create([
            "user_id" => $user->user_id,
            "login_dt" => date("Y-m-d H:i:s"),
            "result" => "SUCCESS",
            "ip" => $_SERVER["REMOTE_ADDR"],
            "user_agent" => $_SERVER["HTTP_USER_AGENT"],
        ])->save();

        //set cookie
        setcookie("access_token", $token, [
            "expires" => time() + 3600 * 8,
            "path" => "/",
            "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
            "secure" => $_ENV["COOKIE_SECURE"] ?? false,
            "httponly" => true,
            "samesite" => $_ENV["COOKIE_SAMESITE"] ?? "Lax"
        ]);
    }
}
