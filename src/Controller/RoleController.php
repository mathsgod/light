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
    #[Right("role.add")]
    public function addRole(\Light\Input\Role $data, #[InjectUser] \Light\Model\User $user): bool
    {
        foreach ($data->childs as $child) {
            $obj = Role::Create([
                'name' => $data->name,
                'child' => $child
            ]);
            $obj->save();
        }

        return true;
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
    public function deleteRole(string $name, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Role::Get(["name" => $name])) return false;
        $obj->delete();

        if (!$obj = Role::Get(["child" => $name])) return false;
        $obj->delete();

        return true;
    }


    #[Mutation]
    #[Logged]
    #[Right('role.update')]
    public function updateRoleChild(string $name, array $childs, #[InjectUser] \Light\Model\User $user): bool
    {
        //remove all roles
        foreach (Role::Query(['name' => $name]) as $role) {
            $role->delete();
        }

        //add all roles
        foreach ($childs as $child) {
            $obj = Role::Create([
                'name' => $name,
                'child' => $child
            ]);
            $obj->save();
        }

        return true;
    }
}
