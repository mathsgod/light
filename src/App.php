<?php

namespace Light;

use Exception;
use GQL\Type\MixedTypeMapperFactory;
use GraphQL\GraphQL;
use GraphQL\Upload\UploadMiddleware;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Permissions\Rbac\Rbac;
use Light\Model\Permission;
use Light\Model\Role;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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

    public function __construct()
    {
        $this->container = new \League\Container\Container();
        $this->factory = new SchemaFactory(new Psr16Cache(new FilesystemAdapter()), $this->container);

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

        $this->factory->addRootTypeMapperFactory(new MixedTypeMapperFactory);
        $this->factory->addTypeMapperFactory(new \R\DB\GraphQLite\Mappers\TypeMapperFactory);



        $this->rbac = new Rbac();
        $this->loadRbac();
    }

    public function loadRbac()
    {
        $this->rbac->setCreateMissingRoles(true);


        /** Roles */
        $this->rbac->addRole("Administrators");
        $this->rbac->addRole("Power Users", ["Administrators"]);
        $this->rbac->addRole("Users", ["Power Users"]);
        $this->rbac->addRole("Everyone", ["Users"]);

        //check if table exists


        try {


            foreach (Role::Query() as $q) {

                if (!$this->rbac->hasRole($q->name)) {
                    $this->rbac->addRole($q->name);
                }

                if (!$this->rbac->hasRole($q->child)) {
                    $this->rbac->addRole($q->child);
                }

                $this->rbac->getRole($q->name)->addChild($this->rbac->getRole($q->child));
            }
        } catch (Exception $e) {
            // may be mysql not ready

        }
        /** Permissions */
        $permissions = Yaml::parseFile(dirname(__DIR__) . '/permissions.yml');
        foreach ($permissions as $role => $permission) {
            foreach ($permission as $p) {
                $this->rbac->getRole($role)->addPermission($p);

                $this->permissions[] = $p;
            }
        }



        //unique
        $this->permissions = array_unique($this->permissions);


        $this->container->add(Rbac::class, $this->rbac);
    }

    public function getPermissions()
    {
        $permissions = $this->permissions;


        try {
            foreach (Permission::Query() as $p) {
                $permissions[] = $p->value;
            }
        } catch (Exception $e) {
        }

        return array_unique($permissions);
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
}
