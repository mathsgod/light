<?php

namespace Light\Controller;

use Light\Model\APIKey;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Query;

class APIKeyController
{
    #[Query]
    #[Logged]
    /**
     * @return \Light\Model\APIKey[]
     * @param ?mixed $filters
     */
    public function listAPIKey(#[InjectUser] \Light\Model\User $user, $filters = [], ?string $sort = ''): \Light\Db\Query
    {
        return APIKey::Query(["user_id" => $user->user_id])->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    public function deleteAPIKey(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = APIKey::Get(["apikey_id" => $id, "user_id" => $user->user_id])) return false;
        $obj->delete();
        return true;
    }
}
