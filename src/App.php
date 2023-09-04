<?php

namespace Light;

use GQL\Type\MixedTypeMapperFactory;
use GraphQL\GraphQL;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Permissions\Rbac\Rbac;
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
    public function __construct()
    {
        $this->container = new \League\Container\Container();
        $this->factory = new SchemaFactory(new Psr16Cache(new FilesystemAdapter()), $this->container);

        $this->factory->addControllerNamespace("\\Light\\Controller\\");
        $this->factory->addTypeNamespace("\\Light\\Model\\");
        $this->factory->addTypeNamespace("\\Light\\Input\\");

        $this->container->add(Controller\AuthController::class);
        $this->container->add(Controller\UserController::class);
        $this->container->add(Controller\RoleController::class);
        $this->container->add(Controller\EventLogController::class);
        $this->container->add(Controller\UserRoleController::class);
        $this->container->add(Controller\PermissionController::class);

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

        foreach (Role::Query() as $q) {

            if (!$this->rbac->hasRole($q->name)) {
                $this->rbac->addRole($q->name);
            }

            if (!$this->rbac->hasRole($q->child)) {
                $this->rbac->addRole($q->child);
            }

            $this->rbac->getRole($q->name)->addChild($this->rbac->getRole($q->child));
        }

        /** Permissions */
        $permissions = Yaml::parseFile(dirname(__DIR__) . '/permissions.yml');
        foreach ($permissions as $role => $permission) {
            foreach ($permission as $p) {
                $this->rbac->getRole($role)->addPermission($p);
            }
        }



        $this->container->add(Rbac::class, $this->rbac);
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

        $request = $request->withAttribute(self::class, $this);

        $auth_service = new Auth\Service($request);

        $this->factory->setAuthenticationService($auth_service);
        $this->factory->setAuthorizationService($auth_service);

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
