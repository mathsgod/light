<?php

namespace Light\Controller;

use Light\Input\AddUser;
use Light\Input\UpdateUser;
use Light\Input\User as InputUser;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Query;
use R\DB\Query as DBQuery;
use TheCodingMachine\GraphQLite\Annotations\HideIfUnauthorized;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Security;

class UserController
{

    #[Query]
    #[Logged]
    /**
     * @return User[]
     * @param ?mixed $filters
     */
    #[Right("ADMIN")]
    public function listUser($filters = [], ?string $sort = ""): DBQuery
    {
        return User::Query()->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    #[Right("ADMIN")]
    public function updateUser(int $id, UpdateUser $data): bool
    {
        $user = User::Get($id);
        $user->bind($data);
        return $user->save();
    }

    #[Mutation]
    #[Right("ADMIN")]
    public function updateUserPassword(int $id, string $password): bool
    {
        if ($user = User::Get($id)) {
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
            return true;
        }
        return false;
    }

    #[Mutation]
    #[Logged]
    public function updateMyPassword(string $old_password, string $new_password, #[InjectUser] User $user): bool
    {
        if (!password_verify($old_password, $user->password)) {
            return false;
        }

        $user->password = password_hash($new_password, PASSWORD_DEFAULT);
        $user->save();
        return true;
    }

    #[Mutation]
    #[Security("is_granted('ADMIN') or (is_granted('POWER_USER'))")]
    public function addUser(InputUser $data): int
    {
        $user = new User();
        $user->bind($data);
        $user->save();

        foreach ($data->roles as $role) {
            $user->addRole($role);
        }
        return $user->user_id;
    }
}
