<?php

namespace Light\Controller;

use Laminas\Permissions\Rbac\Rbac;
use Light\App;
use Light\Model\Permission;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class PermissionController
{

    #[Query]
    /**
     * @param string[] $rights
     * @return string[]
     */
    public function granted(#[Autowire] App $app, #[InjectUser] ?User $user, array $rights): array
    {
        if (!$user) {
            return [];
        }

        $data = [];
        foreach ($rights as $right) {
            if ($user->isGranted($app, $right)) {
                $data[] = $right;
            }
        }
        return $data;
    }


    #[Query()]
    #[Logged]
    #[Right("permission.all")]
    /**
     * @return string[]
     */
    public function allPermission(#[Autowire] App $app): array
    {
        return $app->getPermissions();
    }

    #[Query]
    #[Logged]
    /**
     * @return \Light\Model\Permission[]
     * @param ?mixed $filters
     */
    #[Right("permission.list")]
    public function listPermission(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = ''): \R\DB\Query
    {
        return Permission::Query()->filters($filters)->sort($sort);
    }


    #[Mutation]
    #[Right("role.permission.add")]
    public function addPermission(string $role, string $value): bool
    {
        $permission = Permission::Create([
            'role' => $role,
            'value' => $value
        ]);
        $permission->save();
        return true;
    }

    #[Mutation]
    #[Right("role.permission.add")]
    /**
     * @param string[] $roles
     */
    public function addPermissionRoles(string $value, array $roles): bool
    {
        foreach ($roles as $role) {
            $permission = Permission::Create([
                'role' => $role,
                'value' => $value
            ]);
            $permission->save();
        }

        return true;
    }

    #[Mutation]
    #[Right("role.permission.remove")]
    public function removePermission(string $role, string $value): bool
    {
        if ($permission = Permission::Get([
            'role' => $role,
            'value' => $value
        ])) {
            $permission->delete();
        }

        return true;
    }

    #[Mutation]
    #[Right("permission.delete")]
    public function deletePermission(int $id): bool
    {
        if ($permission = Permission::Get($id)) {
            $permission->delete();
        }

        return true;
    }
}
