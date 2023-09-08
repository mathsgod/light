<?php

namespace Light\Controller;

use Laminas\Permissions\Rbac\Rbac;
use Light\Model\Permission;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class PermissionController
{
    #[Query]
    #[Logged]
    /**
     * @return Permission[]
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
}
