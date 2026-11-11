<?php

namespace Light\Framework\Controller;

use Light\Framework\Input\User as InputUser;
use Light\Framework\GraphQL\Annotations\LoadModel;
use Light\Framework\Type\System;
use Light\Framework\Model\User;
use Light\Framework\Model\UserRole;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\GraphQLite\Annotations\Query;
use \Light\Db\Query as DBQuery;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
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
     * @return \Light\Framework\Model\User[]
     * @param ?mixed $filters
     * @deprecated use { app { users }}
     */
    #[Right("user.list")]
    public function listUser(#[InjectUser] \Light\Framework\Model\User $user, $filters = null, ?string $sort = ""): DBQuery
    {

        //only administrators can list administrators
        $q = User::Query()->filters($filters)->sort($sort);
        if (!$user->is("Administrators")) {

            //filter out administrators
            $q = $q->whereRaw("user_id NOT IN (SELECT user_id FROM UserRole WHERE role = 'Administrators')");
        }

        return $q;
    }

    #[Mutation]
    #[Logged]
    #[Right("user.update")]
    #[Security('obj.canUpdate(user)', failWith: false)]
    public function updateUser(
        #[LoadModel] User $obj,
        #[UseInputType(inputType: "UpdateUserInput")] InputUser $data,
    ): bool {
        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right("user.change-password")]
    #[Security('user.is("Administrators") or not obj.is("Administrators")', failWith: false)]
    public function updateUserPassword(#[LoadModel] User $obj, string $password): bool
    {

        //check is valid password
        $system = new System();
        if (!$system->isValidPassword($password)) {
            throw new \Exception("Password is not valid to the password policy");
        }

        $obj->password = password_hash($password, PASSWORD_DEFAULT);
        $obj->password_dt = date("Y-m-d H:i:s");
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    public function changeUserPassword(string $old_password, string $new_password, #[InjectUser] User $user): bool
    {
        if (!password_verify($old_password, $user->password)) {
            return false;
        }

        //check is valid password
        $system = new System();
        if (!$system->isValidPassword($new_password)) {
            throw new \Exception("Password is not valid to the password policy");
        }


        $user->password = password_hash($new_password, PASSWORD_DEFAULT);
        $user->password_dt = date("Y-m-d H:i:s");
        $user->save();
        return true;
    }

    #[Mutation]
    #[Right("user.create")]
    public function addUser(InputUser $data, #[InjectUser] \Light\Framework\Model\User $current): int
    {

        $user = User::Create();
        $user->bind($data);

        if (!$user->join_date) {
            $user->join_date = date("Y-m-d");
        }

        if ($user->status === null) {
            $user->status = 0;
        }

        if (!$user->language) {
            $user->language = "en";
        }

        //check is valid password
        $system = new System();
        if (!$system->isValidPassword($data->password)) {
            throw new \Exception("Password is not valid to the password policy");
        }

        $user->password = password_hash($data->password, PASSWORD_DEFAULT);
        $user->password_dt = date("Y-m-d H:i:s");

        //check is user exist

        if (User::Query(["username" => $user->username])->count()) {
            throw new \Exception("Username already exist");
        }


        $user->save();

        foreach ($data->roles as $role) {

            //only administrators can add administrators
            if ($role == "Administrators" && !$current->is("Administrators")) {
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
    #[Security('obj.canDelete(user)', failWith: false)]
    public function deleteUser(#[LoadModel] User $obj): bool
    {
        $obj->delete();
        return true;
    }
}
