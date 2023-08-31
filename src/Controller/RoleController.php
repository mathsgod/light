<?php

namespace Light\Controller;

use Light\Model\Role;
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
    public function listRole($filters = [],  ?string $sort = '', #[InjectUser] \Light\Model\User $user): \R\DB\Query
    {
        return Role::Query()->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    #[Right("ADMIN")]
    public function addRole(\Light\Input\Role $data, #[InjectUser] \Light\Model\User $user): int
    {
        $obj = Role::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->role_id;
    }

    #[Mutation]
    #[Logged]
    #[Right("ADMIN")]
    public function updateRole(int $id,  \Light\Input\Role $data, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Role::Get($id)) return false;
        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right("ADMIN")]
    public function removeRole(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Role::Get($id)) return false;
        $obj->delete();
        return true;
    }
}
