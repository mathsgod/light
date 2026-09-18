<?php

namespace Light;

use Exception;
use Light\Auth\TokenManager;
use Light\Auth\AudienceRegistry;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Upload\UploadMiddleware;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use Kcs\ClassFinder\Finder\ComposerFinder;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use League\Flysystem\MountManager;
use Light\Filesystem\FilesystemFactory;
use Light\Rbac\Rbac;
use Light\Model\Config;
use Light\Model\MyFavorite;
use Light\Model\Permission;
use Light\Model\Role;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Model\UserRole;
use Light\Drive\Drive;
use Light\Mailer;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\GraphQLite\Context\Context;
use TheCodingMachine\GraphQLite\SchemaFactory;
use Webauthn\PublicKeyCredentialRpEntity;

class App implements MiddlewareInterface, \League\Event\EventDispatcherAware, RequestHandlerInterface
{

    use \League\Event\EventDispatcherAwareBehavior;

    protected Auth\Service $auth_service;
    protected ?MountManager $mountManager = null;
    protected FilesystemFactory $filesystemFactory;

    protected \League\Container\Container $container;
    protected SchemaFactory $factory;

    protected Rbac $rbac;

    protected string $mode = "dev";

    protected CacheInterface $cache;
    private ?AudienceRegistry $audienceRegistry = null;

    protected array $menus = [];

    protected \Light\Server $server;

    public function __construct()
    {

        //check time zone from config

        if ($tz = $_ENV["TZ"]) {
            date_default_timezone_set($tz);
        }

        $this->container = new \League\Container\Container();
        $this->filesystemFactory = new FilesystemFactory();

        $this->server = new \Light\Server($this->container);

        $this->server->pipe($this);

        $this->container->add(App::class, $this);
        $this->container->add(FilesystemFactory::class, $this->filesystemFactory);
        $this->container->add(MountManager::class, function (): MountManager {
            return $this->getMountManager();
        });
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
            return new Controller\FileManagerController($this);
        });
        $this->container->add(Controller\TranslateController::class);
        $this->container->add(Controller\WebAuthnController::class);
        $this->container->add(Controller\SystemValueController::class);
        $this->container->add(Controller\MyFavoriteController::class);
        $this->container->add(Controller\FileSystemController::class);
        $this->container->add(Controller\RevisionController::class);
        $this->container->add(Controller\DatabaseController::class);
        $this->container->add(Controller\DriveController::class);
        $this->container->add(Controller\CustomFieldController::class);
        $this->container->add(Controller\APIKeyController::class);

        /*        $this->container->delegate(
            new \League\Container\ReflectionContainer()
        );
 */
        Model::SetContainer($this->container);
        $defaultLifetime = 0;
        $debug = true;
        try {
            if ($config = Config::Get(["name" => "mode"])) {
                $this->mode = $config->value;
                if ($this->mode === "prod") {
                    $debug = false;
                    $defaultLifetime = 0;
                } else {
                    $defaultLifetime = 15;
                }
            }
        } catch (Exception $e) {
            $this->mode = "dev";
            $defaultLifetime = 15;
        }

        $gql = new \Light\GraphQL\Server($defaultLifetime, $debug, $this->container);

        $this->cache = $gql->getCache();
        $this->factory = $gql->getSchemaFactory();
        $this->factory->addNamespace("Light");
        $this->factory->addTypeMapperFactory(new \Light\Db\GraphQLite\Mappers\TypeMapperFactory);

        $this->rbac = new Rbac();
        $this->rbac->setPermissionSeparator(".");
        $this->loadRbac();
        $this->loadMenu();
    }

    public function getMountManager(): MountManager
    {
        if ($this->mountManager === null) {
            $this->mountManager = $this->filesystemFactory->createMountManager(
                $this->getFSConfig(),
                $this->getFilesystemUser(),
            );
        }

        return $this->mountManager;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->execute($request);

        try {
            //return new JsonResponse($result->toArray());
            if ($this->isDevMode()) {
                return new JsonResponse($result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE), 200, [], JsonResponse::DEFAULT_JSON_FLAGS | JSON_UNESCAPED_UNICODE);
            } else {
                DocumentValidator::addRule(new DisableIntrospection(true));
                return new JsonResponse($result->toArray(), 200, [], JsonResponse::DEFAULT_JSON_FLAGS | JSON_UNESCAPED_UNICODE);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['errors' => [
                ["message" => $this->isDevMode() ? $e->getMessage() : "(Production mode) Internal Server Error"]
            ]]);
        }
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
                    "permission" => "file_manager.index"
                ]
            ]);
        }
    }


    public function getFlatMenus(): array
    {
        $result = [];
        $stack = $this->menus;
        while (count($stack) > 0) {
            $item = array_shift($stack);
            if ($item["children"]) {
                foreach ($item["children"] as $child) {
                    $stack[] = $child;
                }
            }
            unset($item["children"]);
            $result[] = $item;
        }
        return $result;
    }

    public function getMenus(): array
    {
        return $this->menus;
    }

    public function addMenus(array $menus): void
    {
        foreach ($menus as $m) {
            $this->menus[] = $m;
        }
    }

    public function getDatabase(): \Light\Db\Adapter
    {
        return \Light\Db\Adapter::Create();
    }

    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    public function getMailer(): Mailer
    {
        $mailer = new Mailer(true);

        $driver = Config::Value("mail_driver");

        if ($driver == "sendmail") {
            $mailer->isSendmail();
        }

        if ($driver == "qmail") {
            $mailer->isQmail();
        }

        if ($driver == "gmail") {
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host = "smtp.gmail.com";
            $mailer->Port = 587;
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Username = Config::Value("mail_username");
            $mailer->Password = Config::Value("mail_password");
        }


        if ($driver == "smtp") {
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host = Config::Value("mail_host");
            $mailer->Username = Config::Value("mail_username");
            $mailer->Password = Config::Value("mail_password");
            $mailer->Port = Config::Value("mail_port");
            $mailer->SMTPSecure = Config::Value("mail_encryption");
        }

        if ($mail_from = Config::Value("mail_from")) {

            if ($mail_from_name = Config::Value("mail_from_name")) {
                $mailer->setFrom($mail_from, $mail_from_name);
            } else {
                $mailer->setFrom($mail_from);
            }
        }

        if ($mail_reply_to = Config::Value("mail_reply_to")) {
            if ($mail_reply_to_name = Config::Value("mail_reply_to_name")) {
                $mailer->addReplyTo($mail_reply_to, $mail_reply_to_name);
            } else {
                $mailer->addReplyTo($mail_reply_to);
            }
        }

        return $mailer;
    }

    /**
     * Adds permissions to a given role.
     *
     * @param string $role The role to add permissions to.
     * @param string[] $permissions An array of permissions to add to the role.
     * @return void
     */
    public function addRolePermissions(string $role, array $permissions): void
    {
        if (!$this->rbac->hasRole($role)) {
            $this->rbac->addRole($role);
        }

        $r = $this->rbac->getRole($role);
        foreach ($permissions as $p) {
            $r->addPermission($p);
        }
    }

    public function loadRbac(): void
    {
        /** Roles */
        $this->rbac->addRole("Administrators")->addPermission("#administrators");

        $role = $this->rbac->addRole("Power Users");
        $role->addPermission("#power users");
        $role->addParent("Administrators");

        $this->rbac->addRole("Users");
        $this->rbac->getRole("Users")->addPermission("#users");
        $this->rbac->getRole("Users")->addParent("Power Users");


        $this->rbac->addRole("Everyone");
        $this->rbac->getRole("Everyone")->addPermission("#everyone");
        $this->rbac->getRole("Everyone")->addParent("Users");

        try {
            foreach (Role::Query() as $q) {
                $this->rbac->addRole($q->name)->addPermission("#" . strtolower($q->name));

                $this->rbac->addRole($q->child)->addPermission("#" . strtolower($q->child));

                $this->rbac->getRole($q->name)->addChild($q->child);
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
            if ($p->role) {
                $role = $this->rbac->addRole($p->role);
                $role->addPermission($p->value);
            }

            if ($p->user_id) {
                $user = $this->rbac->addUser($p->user_id);
                $user->addPermission($p->value);
            }
        }

        //load user role
        foreach (UserRole::Query() as $ur) {
            $role = $this->rbac->addRole($ur->role);
            $role->addPermission("#" . strtolower($ur->role));

            $this->rbac->addUser($ur->user_id, [$ur->role]);
        }

        $this->container->add(Rbac::class, $this->rbac);
    }

    private function getMenusPermission(array $menus): array
    {
        $p = [];
        foreach ($menus as $m) {
            if (isset($m["permission"]) && $m["permission"]) {
                if (is_array($m["permission"])) {
                    foreach ($m["permission"] as $p_) {
                        $p[] = $p_;
                    }
                } else {
                    $p[] = $m["permission"];
                }
            }

            if (isset($m["children"]) && $m["children"]) {
                foreach ($this->getMenusPermission($m["children"]) as $c_p) {
                    $p[] = $c_p;
                }
            }
        }

        return $p;
    }

    public function getPermissions(): array
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

        foreach (glob(__DIR__ . "/Database/*.php") as $file) {
            $class = "Light\\Database\\" . basename($file, ".php");
            $rc = new \ReflectionClass($class);
            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }

        foreach (glob(__DIR__ . "/Type/*.php") as $file) {
            $class = "Light\\Type\\" . basename($file, ".php");
            $rc = new \ReflectionClass($class);
            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }

        $finder = new ComposerFinder();
        foreach ($finder->inNamespace("Controller") as $class => $reflector) {
            $reflector = new \ReflectionClass($class);
            foreach ($reflector->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }
        foreach ($finder->inNamespace("Model") as $class => $reflector) {
            $reflector = new \ReflectionClass($class);
            foreach ($reflector->getMethods() as $method) {
                foreach ($method->getAttributes("TheCodingMachine\GraphQLite\Annotations\Right") as $attr) {
                    $permissions[] = $attr->getArguments()[0];
                }
            }
        }

        foreach ($this->getMenusPermission($this->menus) as $p) {
            $permissions[] = $p;
        }


        foreach ($this->rbac->getPermissions() as $permission) {
            $permissions[] = $permission;
        }

        $permissions = self::expandPermissions($permissions);

        //sort
        sort($permissions);
        return $permissions;
    }

    /**
     * Expand a flat permission list by adding index permissions for explicit
     * wildcards and parent wildcard permissions whenever a parent has two or
     * more direct children. Plain parent nodes are not added because the RBAC
     * matcher only recognises exact permissions and `.*` wildcards.
     *
     * @param string[] $permissions
     * @return string[]
     */
    public static function expandPermissions(array $permissions): array
    {
        $permissions = array_filter($permissions, fn($p) => is_string($p) && $p !== "" && $p[0] !== "#");
        $permissions = array_unique($permissions);
        $explicit = array_flip($permissions);

        /** @var array<string, array<string, mixed>> $tree */
        $tree = [];
        foreach ($permissions as $p) {
            $parts = explode(".", $p);
            /** @var array<string, mixed> $node */
            $node = &$tree;
            foreach ($parts as $part) {
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }
                /** @var array<string, mixed> $node */
                $node = &$node[$part];
            }
            unset($node);
        }

        $collect = function (array $node, string $prefix = "") use (&$collect, $explicit): array {
            $result = [];
            foreach ($node as $key => $children) {
                $current = $prefix === "" ? $key : $prefix . "." . $key;
                $isLeaf = count($children) === 0;
                $hasWildcard = count($children) >= 2;
                $isExplicit = isset($explicit[$current]);

                if ($isLeaf || $isExplicit) {
                    $result[] = $current;
                }
                if ($hasWildcard) {
                    $result[] = $current . ".*";
                }
                $result = array_merge($result, $collect($children, $current));
            }
            return $result;
        };

        $result = $collect($tree);

        $indexPermissions = [];
        foreach ($result as $p) {
            if (str_ends_with($p, ".*")) {
                $indexPermissions[] = substr($p, 0, -2) . ".index";
            }
        }

        return array_values(array_unique(array_merge($result, $indexPermissions)));
    }


    public function isTwoFactorAuthentication(): bool
    {
        return Config::Value("two_factor_authentication") ? true : false;
    }

    public function getRbac(): Rbac
    {
        return $this->rbac;
    }

    public function getAudienceRegistry(): AudienceRegistry
    {
        return $this->audienceRegistry ??= new AudienceRegistry();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getSchemaFactory(): SchemaFactory
    {
        return $this->factory;
    }

    public function getAuthService(): Auth\Service
    {
        return $this->auth_service;
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

        $uploadCapture = new class($request) implements RequestHandlerInterface {
            public ServerRequestInterface $captured;
            public function __construct(ServerRequestInterface $r) { $this->captured = $r; }
            public function handle(ServerRequestInterface $r): ResponseInterface { $this->captured = $r; return new EmptyResponse(); }
        };
        (new UploadMiddleware())->process($request, $uploadCapture);
        $request = $uploadCapture->captured;

        $request = $request->withAttribute(self::class, $this);


        $auth_service = new Auth\Service($request);
        $this->auth_service = $auth_service;
        $this->mountManager = $this->filesystemFactory->createMountManager(
            $this->getFSConfig(),
            $this->getFilesystemUser(),
        );

        $this->factory->setAuthenticationService($auth_service);
        $this->factory->setAuthorizationService($auth_service);


        $this->container->add(ServerRequestInterface::class, $request, true);
        $this->container->add(Auth\Service::class, $auth_service, true);

        return $handler->handle($request);
    }


    public function getDriveResponse(int $index, string $path): ResponseInterface
    {
        $config = json_decode(Config::Value("fs", "[]"), true);
        if (count($config) == 0) {
            $config[] = ["name" => "default"];
        }

        if ($index < 0 || $index >= count($config)) {
            return new \Laminas\Diactoros\Response\EmptyResponse(404);
        }

        $drive = $this->getDrive($index);
        $fs = $drive->getFilesystem();

        if (!$fs->has($path)) {
            return new \Laminas\Diactoros\Response\EmptyResponse(404);
        }



        $response = new \Laminas\Diactoros\Response();
        $response = $response->withHeader("Content-Type", $fs->mimeType($path));
        $response->getBody()->write($fs->read($path));
        return $response;
    }


    public function execute(ServerRequestInterface $request): \GraphQL\Executor\ExecutionResult
    {
        $uploadCapture = new class($request) implements RequestHandlerInterface {
            public ServerRequestInterface $captured;
            public function __construct(ServerRequestInterface $r) { $this->captured = $r; }
            public function handle(ServerRequestInterface $r): ResponseInterface { $this->captured = $r; return new EmptyResponse(); }
        };
        (new UploadMiddleware())->process($request, $uploadCapture);
        $request = $uploadCapture->captured;

        $body = $request->getParsedBody();
        $query = $body["query"] ?? null;
        $variableValues = $body["variables"] ?? null;


        $schema = $this->factory->createSchema();
        return  GraphQL::executeQuery($schema, $query, null, new Context, $variableValues);
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
        //15 minutes default
        return intval(Config::Value("access_token_expire", 900));
    }

    public function getRefreshTokenExpire(): int
    {
        //7 days default
        return intval(Config::Value("refresh_token_expire", 604800));
    }

    public function setAccessTokenCookie(string $token): void
    {
        $samesite = $_ENV["COOKIE_SAMESITE"] ?? "Lax";
        // if is https then add Partitioned
        if ($_SERVER["HTTPS"] == "on" && $_ENV["COOKIE_PARTITIONED"] == "true") {
            $samesite .= ";Partitioned";
        }
        //set cookie
        setcookie("access_token", $token, [
            "path" => "/",
            "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
            "secure" => $_ENV["COOKIE_SECURE"] ?? false,
            "httponly" => true,
            "samesite" => $samesite
        ]);
    }

    public function getRefreshTokenCookiePath(): string
    {
        if ($_ENV["API_PREFIX"]) {
            $currentDir = rtrim($_ENV["API_PREFIX"], '/\\');
        } else {
            $currentDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        }
        return $currentDir . "/refresh_token";
    }

    public function setRefreshTokenCookie(string $token, int $expire): void
    {
        setcookie("refresh_token", $token, [
            "path" => $this->getRefreshTokenCookiePath(),
            "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
            "secure" => $_ENV["COOKIE_SECURE"] ?? false,
            "httponly" => true,
            "samesite" => $_ENV["COOKIE_SAMESITE"] ?? "Lax",
            "expires" => time() + $expire
        ]);
    }

    public function userLogin(User $user): void
    {
        // Successful credential login clears any prior session-revocation lock
        // caused by refresh-token reuse detection, allowing the user to start a
        // new legitimate session. Old refresh tokens remain revoked individually.
        $this->getCache()->delete("user_sessions_revoked_" . $user->user_id);

        $access_token_expire = $this->getAccessTokenExpire();
        $session_id = Uuid::uuid4()->toString();
        $access_jti = $session_id;

        $payload = [
            "iss" => "light server",
            "jti" => $access_jti,
            "sid" => $session_id,
            "iat" => time(),
            "exp" => time() + $access_token_expire,
            "role" => "Users",
            "id" => $user->user_id,
            "type" => "access_token"
        ];


        $this->setAccessTokenCookie(TokenManager::encode($payload));

        //save UserLog
        UserLog::_table()->insert([
            "user_id" => $user->user_id,
            "login_dt" => date("Y-m-d H:i:s"),
            "result" => "SUCCESS",
            "ip" => $_SERVER["REMOTE_ADDR"],
            "user_agent" => $_SERVER["HTTP_USER_AGENT"],
            "jti" => $session_id
        ]);

        //set refresh token — uses independent jti from access token
        $refresh_token_expire = intval(Config::Value("refresh_token_expire", 3600 * 24 * 7));
        $refresh_jti = Uuid::uuid4()->toString();
        $refresh_payload = [
            "iss" => "light server",
            "jti" => $refresh_jti,
            "sid" => $session_id,
            "iat" => time(),
            "exp" => time() + $refresh_token_expire,
            "id" => $user->user_id,
            "type" => "refresh_token"
        ];


        $refresh_token = TokenManager::encode($refresh_payload);
        $this->setRefreshTokenCookie($refresh_token, $refresh_token_expire);
    }

    public function hasFavorite(): bool
    {

        $result =  iterator_to_array(MyFavorite::GetAdapter()->query("Show tables like 'MyFavorite'")->execute());
        if (count($result) == 0) return false;
        return true;
    }

    public function getFSConfig(): array
    {
        $config = Config::Get(["name" => "fs"]);
        if (!$config) {
            $fss = [];
        } else {
            $fss = json_decode($config->value ?? "[]", true);
        }
        //push default if not exists
        if (count($fss) == 0) {
            $fss[] = [
                "name" => "local",
                "type" => "local",
                "data" => [
                    "location" => getcwd() . "/uploads",
                    "public_url" => "/api/uploads/"
                ]
            ];
        }
        return $fss;
    }

    public function getFS(int $index = 0): \League\Flysystem\FilesystemOperator
    {
        $fss = $this->getFSConfig();
        if (!isset($fss[$index])) {
            throw new \RuntimeException('Filesystem not found');
        }

        return $this->filesystemFactory->createFilesystem(
            $fss[$index],
            $this->getFilesystemUser(),
        );
    }

    private function getFilesystemUser(): ?User
    {
        if (!isset($this->auth_service)) {
            return null;
        }

        try {
            return $this->auth_service->getUser();
        } catch (TokenExpiredException) {
            return null;
        }
    }

    public function isRevisionEnabled(string $model): bool
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

    public function getRpId(): string
    {
        $name = $_SERVER["SERVER_NAME"];
        if ($name == "0.0.0.0") {
            $name = "localhost";
        }
        if ($_ENV["RP_ID"]) {
            return $_ENV["RP_ID"];
        }
        return $name;
    }

    public function getRpEntity(): PublicKeyCredentialRpEntity
    {
        $name = $_SERVER["SERVER_NAME"];
        if ($name == "0.0.0.0") {
            $name = "localhost";
        } else {
            $name = $_SERVER["SERVER_NAME"];
        }

        $rpEntity = PublicKeyCredentialRpEntity::create(
            $name, //Name
            $this->getRpId(),              //ID
            null                            //Icon
        );

        return $rpEntity;
    }

    public function getDrive(int $index): Drive
    {
        $config = $this->getFSConfig();
        if (!isset($config[$index])) {
            throw new \RuntimeException('Filesystem not found');
        }
        $fs = $config[$index];
        return new Drive($fs["name"], $this->getFS($index), $index, $fs["data"]);
    }

    public function handleRefreshToken(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getCookieParams()["refresh_token"] ?? null;
        try {
            if (!$token) {
                throw new Exception("No refresh token", 401);
            }

            $payload = TokenManager::decode($token);
            if ($payload->type != "refresh_token") {
                throw new Exception("Invalid token", 401);
            }

            $user = User::Get($payload->id);
            if (!$user) {
                throw new Exception("User not found", 404);
            }

            $cache = $this->getCache();
            $user_id = $user->user_id;
            $old_refresh_jti = $payload->jti;
            $refresh_token_expire = $this->getRefreshTokenExpire();
            $session_id = !empty($payload->sid) ? (string) $payload->sid : null;

            $revoked_key = "revoked_refresh_token_" . $old_refresh_jti;
            $grace_key = "refresh_token_grace_" . $old_refresh_jti;

            if (
                $session_id
                && $cache->has(Auth\Service::REVOKED_SESSION_PREFIX . $session_id)
            ) {
                throw new Exception("Session revoked", 401);
            }

            // Reuse detection: if this refresh token's jti was already used
            if ($cache->has($revoked_key)) {
                // Within a short grace period, treat reuse as a benign race condition
                // (e.g. two tabs refreshing at the same time). Re-issue the same tokens.
                $grace = $cache->get($grace_key);
                if ($grace && is_array($grace) && !empty($grace['access_token']) && !empty($grace['refresh_token'])) {
                    $this->setAccessTokenCookie($grace['access_token']);
                    $this->setRefreshTokenCookie($grace['refresh_token'], $refresh_token_expire);
                    return new TextResponse("Token refreshed", 200);
                }

                // Reuse after grace period: treat as token theft
                $cache->set("user_sessions_revoked_" . $user_id, true, $refresh_token_expire);
                throw new Exception("Token reuse detected", 401);
            }

            // Mark old refresh token as used (rotation)
            $cache->set($revoked_key, true, $refresh_token_expire);

            // Refresh tokens issued before stable session IDs existed do
            // not have a sid. Migrate them into a new tracked browser
            // session without invalidating the user's existing login.
            if (!$session_id) {
                $session_id = Uuid::uuid4()->toString();
                UserLog::_table()->insert([
                    "user_id" => $user_id,
                    "login_dt" => date("Y-m-d H:i:s"),
                    "last_access_time" => date("Y-m-d H:i:s"),
                    "result" => "SUCCESS",
                    "ip" => $request->getServerParams()["REMOTE_ADDR"] ?? ($_SERVER["REMOTE_ADDR"] ?? "unknown"),
                    "user_agent" => $request->getHeaderLine("User-Agent") ?: ($_SERVER["HTTP_USER_AGENT"] ?? "unknown"),
                    "jti" => $session_id
                ]);
            } else {
                $user->saveLastAccessTime($session_id);
            }

            // Issue new access token (new jti)
            $access_token_expire = $this->getAccessTokenExpire();
            $access_jti = Uuid::uuid4()->toString();
            $access_payload = [
                "iss" => "light server",
                "jti" => $access_jti,
                "sid" => $session_id,
                "iat" => time(),
                "exp" => time() + $access_token_expire,
                "role" => "Users",
                "id" => $user->user_id,
                "type" => "access_token"
            ];
            $access_token = TokenManager::encode($access_payload);
            $this->setAccessTokenCookie($access_token);

            // Issue new refresh token (new jti) — rotation
            $new_refresh_jti = Uuid::uuid4()->toString();
            $refresh_payload = [
                "iss" => "light server",
                "jti" => $new_refresh_jti,
                "sid" => $session_id,
                "iat" => time(),
                "exp" => time() + $refresh_token_expire,
                "id" => $user->user_id,
                "type" => "refresh_token"
            ];
            $refresh_token = TokenManager::encode($refresh_payload);
            $this->setRefreshTokenCookie($refresh_token, $refresh_token_expire);

            // Cache the issued token pair for a short grace period to handle multi-tab races
            $cache->set($grace_key, [
                "access_token" => $access_token,
                "refresh_token" => $refresh_token,
            ], 5);

            return new TextResponse("Token refreshed", 200);
        } catch (Exception $e) {
            //clear access token cookie
            setcookie("access_token", "", [
                "path" => "/",
                "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
                "secure" => $_ENV["COOKIE_SECURE"] ?? false,
                "httponly" => true,
                "samesite" => $_ENV["COOKIE_SAMESITE"] ?? "Lax",
                "expires" => time() - 3600
            ]);
            //clear refresh token cookie
            setcookie("refresh_token", "", [
                "path" => $this->getRefreshTokenCookiePath(),
                "domain" => $_ENV["COOKIE_DOMAIN"] ?? "",
                "secure" => $_ENV["COOKIE_SECURE"] ?? false,
                "httponly" => true,
                "samesite" => $_ENV["COOKIE_SAMESITE"] ?? "Lax",
                "expires" => time() - 3600
            ]);
            return new TextResponse($e->getMessage(), 401);
        }
    }



    public function run(): void
    {
        $router = $this->server->getRouter();

        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');


        $router->map("GET", $basePath . "/fs/{protocol}/{path:.*}", function (ServerRequestInterface $request, array $args) {

            $auth = new Auth\Service($request);
            if ($auth->isLogged()) {
                $location = $args["protocol"] . "://" . urldecode($args["path"]);
                if ($this->getMountManager()->has($location)) {
                    $stream = $this->getMountManager()->readStream($location);
                    $response = new \Laminas\Diactoros\Response();
                    $response = $response->withHeader("Content-Type", $this->getMountManager()->mimeType($location));
                    $response->getBody()->write(stream_get_contents($stream));
                    fclose($stream);
                    return $response;
                }
            }
            return new TextResponse("Unauthorized", 401);
        });

        $router->map("GET", $basePath . "/drive/{index}/{path:.*}", function (ServerRequestInterface $request, array $args) {

            $auth = new Auth\Service($request);

            if ($auth->isLogged()) {
                return $this->getDriveResponse($args["index"], $args["path"]);
            }
            return new TextResponse("Unauthorized", 401);
        });

        $refreshHandler = function (ServerRequestInterface $request): ResponseInterface {
            return $this->handleRefreshToken($request);
        };

        $router->map('POST', $basePath . '/refresh_token', $refreshHandler);
        $router->map('POST', $basePath . '/api/refresh_token', $refreshHandler);
        $jwksHandler = function (): ResponseInterface {
            if (TokenManager::algorithm() !== 'RS256') {
                return new TextResponse('JWKS is not available', 404);
            }

            return (new JsonResponse(TokenManager::jwks()))
                ->withHeader('Cache-Control', 'public, max-age=300');
        };
        $router->map('GET', '/.well-known/jwks.json', $jwksHandler);
        if ($basePath !== '') {
            $router->map('GET', $basePath . '/.well-known/jwks.json', $jwksHandler);
        }
        $this->server->run();
    }

    public function getIpLocation(string $ip): mixed
    {

        $data = $this->cache->get("geoip_" . $ip);
        if ($data) {
            return $data;
        }

        $url = "http://ip-api.com/json/{$ip}";
        $response = @file_get_contents($url);
        $data = json_decode($response, true);

        $this->cache->set("geoip_" . $ip, $data, 60);

        return $data;
    }
}
