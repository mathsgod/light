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
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Light\Model\Config;
use Light\Model\MyFavorite;
use Light\Model\Permission;
use Light\Model\Role;
use Light\Model\User;
use Light\Model\UserLog;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use R\DB\Schema;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\ClassExplorer\Glob\GlobClassExplorer;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\SchemaFactory;

class App implements MiddlewareInterface
{
    protected $container;
    protected $factory;

    protected $rbac;
    protected $permissions = [];

    protected $mode = "dev";

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
        $this->container->add(Controller\FileManagerController::class, function () {
            return new Controller\FileManagerController($this->getFS());
        });
        $this->container->add(Controller\TranslateController::class);
        $this->container->add(Controller\WebAuthnController::class);
        $this->container->add(Controller\SystemValueController::class);
        $this->container->add(Controller\MyFavoriteController::class);
        $this->container->add(Controller\FileSystemController::class);
        $this->container->add(Controller\RevisionController::class);

        /*        $this->container->delegate(
            new \League\Container\ReflectionContainer()
        );
 */
        Model::GetSchema()->setContainer($this->container);

        $this->factory->addRootTypeMapperFactory(new MixedTypeMapperFactory);
        $this->factory->addTypeMapperFactory(new \R\DB\GraphQLite\Mappers\TypeMapperFactory);

        $this->rbac = new Rbac();
        $this->loadRbac();

        try {
            if ($config = Config::Get(["name" => "mode"])) {
                $this->mode = $config->value;
                if ($this->mode === "prod") {
                    $this->factory->prodMode();
                } else {
                    $this->factory->devMode();
                }
            }
        } catch (Exception $e) {
            $this->factory->devMode();
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
                if (is_array($m["permission"])) {
                    foreach ($m["permission"] as $p_) {
                        $p[] = $p_;
                    }
                } else {
                    $p[] = $m["permission"];
                }
            }

            if ($m["children"]) {
                foreach ($this->getMenusPermission($m["children"]) as $c_p) {
                    $p[] = $c_p;
                }
            }
        }

        return $p;
    }

    public function getPermissions()
    {
        $permissions = [];
        foreach (glob(__DIR__ . "/Controller/*.php") as $file) {
            $class = "Light\\Controller\\" . basename($file, ".php");
            $rc = new \ReflectionClass($class);
            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }

        foreach (glob(__DIR__ . "/Model/*.php") as $file) {
            $class = "Light\\Model\\" . basename($file, ".php");
            $rc = new \ReflectionClass($class);
            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }

        $explorer = new GlobClassExplorer("Controller", $this->getCache());
        foreach ($explorer->getClasses() as $class) {
            $rc = new \ReflectionClass($class);
            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }



        $explorer = new GlobClassExplorer("Model", $this->getCache());
        foreach ($explorer->getClasses() as $class) {
            $rc = new \ReflectionClass($class);
            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }









        $permissions = array_merge($this->permissions, $permissions);
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

        //filter # from permissions
        $permissions = array_filter($permissions, function ($p) {
            return $p[0] != "#";
        });

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

        if (!$request->getParsedBody()) {
            $body = json_decode(file_get_contents('php://input'), true);
            $request = $request->withParsedBody($body);
        }

        $uploadMiddleware = new UploadMiddleware();
        $request = $uploadMiddleware->processRequest($request);

        $request = $request->withAttribute(self::class, $this);

        $puxt = $request->getAttribute(\PUXT\App::class);

        if (assert($puxt instanceof \PUXT\App)) {
            $puxt->addAttributeMiddleware(new \Light\Attributes\Logged);
            $puxt->addParameterHandler(InjectUser::class, new \Light\ParameterHandlers\InjectedUser);
        }


        $auth_service = new Auth\Service($request);

        $this->factory->setAuthenticationService($auth_service);
        $this->factory->setAuthorizationService($auth_service);


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
        return $this->mode != "prod";
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

    public function getAccessTokenExpire(): int
    {
        return intval(Config::Value("access_token_expire", 3600 * 8));
    }

    public function userLogin(User $user)
    {
        $access_token_expire = $this->getAccessTokenExpire();
        $jti = Uuid::uuid4()->toString();
        $payload = [
            "iss" => "light server",
            "jti" => $jti,
            "iat" => time(),
            "exp" => time() + $access_token_expire,
            "role" => "Users",
            "id" => $user->user_id,
            "type" => "access_token"
        ];

        $token = JWT::encode($payload, $_ENV["JWT_SECRET"], "HS256");

        //save UserLog
        UserLog::_table()->insert([
            "user_id" => $user->user_id,
            "login_dt" => date("Y-m-d H:i:s"),
            "result" => "SUCCESS",
            "ip" => $_SERVER["REMOTE_ADDR"],
            "user_agent" => $_SERVER["HTTP_USER_AGENT"],
            "jti" => $jti
        ]);

        $samesite = $_ENV["COOKIE_SAMESITE"] ?? "Lax";
        // if is https then add Partitioned
        if ($_SERVER["HTTPS"] == "on") {
            $samesite .= ";Partitioned";
        }
        //set cookie
        setcookie("access_token", $token, [
            "expires" => time() + $access_token_expire,
            "path" => "/",
            "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
            "secure" => $_ENV["COOKIE_SECURE"] ?? false,
            "httponly" => true,
            "samesite" => $samesite
        ]);
    }

    public function hasFavorite(): bool
    {

        $result = MyFavorite::GetSchema()->query("Show tables like 'MyFavorite'")->fetchAll();
        if (count($result) == 0) return false;
        return true;
    }

    public function getFS(string $name = "default")
    {
        $config = Config::Get(["name" => "fs"]);

        if (!$config) {
            $fss = [];
        } else {
            $fss = json_decode($config->value, true);
        }

        //map name to index
        $fss = array_combine(array_column($fss, "name"), $fss);


        //push default if not exists
        if (!isset($fss["default"])) {
            $fss["default"] = [
                "name" => "default",
                "type" => "local",
                "data" => [
                    "location" => getcwd() . "/uploads"
                ]
            ];
        }

        $fs = $fss[$name];

        if ($fs["type"] == "local") {
            $data = $fs["data"];

            $visibilityConverter = PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0640,
                    'private' => 0640,
                ],
                'dir' => [
                    'public' => 0777,
                    'private' => 0777,
                ],
            ]);


            $location = $data["location"];
            $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($location, $visibilityConverter);
            $filesystem = new \League\Flysystem\Filesystem($adapter);
            return $filesystem;
        }

        if ($fs["type"] == "S3") {
            $data = $fs["data"];
            $client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $data["region"],
                'endpoint' => $data["endpoint"],
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $data["accessKey"],
                    'secret' => $data["secretKey"],
                ],
            ]);
            // The internal adapter
            $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                // S3Client
                $client,
                // Bucket name
                $data['bucket'],
                // Optional path prefix
                $data["prefix"],
                // Visibility converter (League\Flysystem\AwsS3V3\VisibilityConverter)
                new \League\Flysystem\AwsS3V3\PortableVisibilityConverter(
                    // Optional default for directories
                    $data["visibility"]
                )
            );

            // The FilesystemOperator
            return new \League\Flysystem\Filesystem($adapter);
        }

        if ($fs["type"] == "hostlink") {
            $data = $fs["data"];
            $adapter = new \HL\Storage\Adapter($data["token"], $data["endpoint"]);
            return  new \League\Flysystem\Filesystem($adapter);
        }

        throw new \Exception("File system not found");
    }

    public function isRevisionEnabled(string $model)
    {
        if (!$config = Config::Get(["name" => "revision"])) {
            return false;
        }

        $revisions = explode(",", $config->value) ?? [];

        if (!in_array($model, $revisions)) {
            return false;
        }

        return true;
    }
}
