<?php

namespace Light\Controller;

use Light\Model\Role;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Query;
use R\DB\Query as DBQuery;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;

class RoleController
{

    #[Query]
    #[Logged]
    /**
     * @return Role[]
     * @param ?mixed $filters
     */
    public function listRole(#[InjectUser] User $user, $filters = [], ?string $sort = ""): DBQuery
    {
        return Role::Query()->filters($filters)->sort($sort);
    }
}
