<?php

namespace Light\Controller;

use Laminas\Permissions\Rbac\Rbac;
use Light\Model\Role;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class RoleController
{
    #[Query]
    #[Logged]
    /**
     * @return Role[]
     * @param ?mixed $filters
     */
    #[Right("role.list")]
    public function listRole(#[Autowire] Rbac $rbac, #[InjectUser] \Light\Model\User $user): array
    {
        $rs = [];
        foreach ($rbac->getRoles() as $role) {
            $rs[] = Role::LoadByRole($role);
        }
        return $rs;
    }

    #[Mutation]
    #[Logged]
    #[Right("role.create")]
    public function addRole(\Light\Input\Role $data, #[InjectUser] \Light\Model\User $user): int
    {
        $obj = Role::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->role_id;
    }

    #[Mutation]
    #[Logged]
    #[Right("role.update")]
    public function updateRole(int $id,  \Light\Input\Role $data, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Role::Get($id)) return false;
        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right("role.delete")]
    public function removeRole(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Role::Get($id)) return false;
        $obj->delete();
        return true;
    }
}
