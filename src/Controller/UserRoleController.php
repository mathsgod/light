<?php

namespace Light\Controller;

use Laminas\Permissions\Rbac\Rbac;
use Light\Model\UserRole;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;

class UserRoleController
{
    #[Mutation]
    #[Logged]
    #[Right("user.role.add")]
    public function addUserRole(#[Autowire] Rbac $rbac, int $user_id, string $role, #[InjectUser] \Light\Model\User $user): bool
    {
        if ($role == "Administrators") { // Only administrators can add administrators
            if (!$user->is("Administrators")) {
                return false;
            }
        }

        $ur = UserRole::Create(["user_id" => $user_id, "role" => $role]);
        $ur->save();
        return true;
    }
}
