<?php

namespace Light\Controller;

use Light\Input\User as InputUser;
use Light\Model\User;
use Light\Model\UserRole;
use TheCodingMachine\GraphQLite\Annotations\Query;
use R\DB\Query as DBQuery;
use TheCodingMachine\GraphQLite\Annotations\FailWith;
use TheCodingMachine\GraphQLite\Annotations\HideIfUnauthorized;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Security;
use TheCodingMachine\GraphQLite\Annotations\UseInputType;

class UserController
{

    #[Query]
    #[Logged]
    /**
     * @return User[]
     * @param ?mixed $filters
     */
    #[Right("user.list")]
    public function listUser(#[InjectUser] \Light\Model\User $user, $filters = [], ?string $sort = ""): DBQuery
    {
        //only administrators can list administrators
        $q = User::Query()->filters($filters)->sort($sort);
        if (!$user->is("Administrators")) {

            //filter out administrators
            $q->where("user_id NOT IN (SELECT user_id FROM UserRole WHERE role = 'Administrators')");
        }

        return $q;
    }

    #[Mutation]
    #[Logged]
    #[Right("user.update")]
    public function updateUser(
        int $id,
        #[UseInputType(inputType: "UpdateUserInput")] InputUser $data,
        #[InjectUser] \Light\Model\User $user
    ): bool {
        $obj = User::Get($id);

        if (!$obj->canUpdate($user)) return false;


        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Right("user.changePassword")]
    public function updateUserPassword(int $id, string $password, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = $this->listUser($user, ["user_id" => $id], "")->first()) return false;
        $obj->password = password_hash($password, PASSWORD_DEFAULT);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    public function updatePassword(string $old_password, string $new_password, #[InjectUser] User $user): bool
    {
        if (!password_verify($old_password, $user->password)) {
            return false;
        }

        $user->password = password_hash($new_password, PASSWORD_DEFAULT);
        $user->save();
        return true;
    }

    #[Mutation]
    #[Right("user.add")]
    public function addUser(InputUser $data, #[InjectUser] \Light\Model\User $user): int
    {
        $user = new User();
        $user->bind($data);

        $user->password = password_hash($data->password, PASSWORD_DEFAULT);

        $user->save();

        foreach ($data->roles as $role) {

            //only administrators can add administrators
            if ($role == "Administrators" && !$user->is("Administrators")) {
                continue;
            }

            UserRole::Create([
                "user_id" => $user->user_id,
                "role" => $role
            ])->save();
        }
        return $user->user_id;
    }

    #[Mutation]
    #[Logged]
    #[Right("user.delete")]
    public function deleteUser(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = User::Get($id)) return false;
        if (!$obj->canDelete($user)) return false;
        $obj->delete();
        return true;
    }
}
