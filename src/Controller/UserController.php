<?php

namespace Light\Controller;

use Light\Input\User as InputUser;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Query;
use R\DB\Query as DBQuery;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;

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
    public function updateUser(int $id, InputUser $data): bool
    {
        $user = User::Get($id);
        $user->first_name = $data->first_name;
        $user->last_name = $data->last_name;
        $user->phone = $data->phone;
        $user->email = $data->email;
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
}
