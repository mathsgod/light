<?php

namespace Light\Controller;

use Laminas\Permissions\Rbac\Rbac;
use Light\Model\UserRole;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Security;

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


    #[Mutation]
    #[Logged]
    #[Security("is_granted('user.role.add') and is_granted('user.role.remove')")]
    /**
     * @param string[] $roles
     */
    public function updateUserRole(#[Autowire] Rbac $rbac, int $user_id, array $roles, #[InjectUser] \Light\Model\User $user): bool
    {
        foreach ($roles as $role) {
            if ($role == "Administrators") { // Only administrators can add administrators
                if (!$user->is("Administrators")) {
                    return false;
                }
            }

            if (!UserRole::Get(["user_id" => $user_id, "role" => $role])) {
                $ur = UserRole::Create(["user_id" => $user_id, "role" => $role]);
                $ur->save();
            }
        }

        //remove all roles that are not in the list
        $user_roles = UserRole::Query(["user_id" => $user_id]);
        foreach ($user_roles as $user_role) {

            if (!in_array($user_role->role, $roles)) {

                if ($role->role == "Administrators") { // Only administrators can remove administrators
                    if (!$user->is("Administrators")) {
                        return false;
                    }
                }

                $user_role->delete();
            }
        }

        return true;
    }
}