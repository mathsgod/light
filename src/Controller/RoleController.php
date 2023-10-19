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
    public function getRole(#[Autowire] Rbac $rbac, string $name): ?Role
    {
        if (!$rbac->hasRole($name)) return null;
        return Role::LoadByRole($rbac->getRole($name));
    }

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

        foreach (Role::Query(['name' => $name]) as $role) {
            $role->delete();
        }

        return true;
    }


    #[Mutation]
    #[Logged]
    #[Right('role.update')]
    /**
     * @param string[] $childs
     */
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

    #[Mutation]
    #[Logged]
    #[Right('#administrators')]
    public function removeRoleChild(string $name, string $child): bool
    {
        if ($role = Role::Get(['name' => $name, 'child' => $child])) {
            $role->delete();
        }
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right('#administrators')]
    public function addRoleChild(string $name, string $child): bool
    {
        if ($name == $child) return false;
        if (Role::Get(['name' => $name, 'child' => $child])) {
            return false;
        }

        $obj = Role::Create([
            'name' => $name,
            'child' => $child
        ]);
        $obj->save();
        return true;
    }
}
