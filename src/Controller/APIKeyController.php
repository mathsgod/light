<?php

namespace Light\Framework\Controller;

use Light\Framework\Model\APIKey;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Query;

class APIKeyController
{
    #[Query]
    #[Logged]
    /**
     * @return \Light\Framework\Model\APIKey[]
     * @param ?mixed $filters
     */
    public function listAPIKey(#[InjectUser] \Light\Framework\Model\User $user, $filters = null, ?string $sort = ''): \Light\Db\Query
    {
        return APIKey::Query(["user_id" => $user->user_id])->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    public function deleteAPIKey(int $id, #[InjectUser] \Light\Framework\Model\User $user): bool
    {
        if (!$obj = APIKey::Get(["apikey_id" => $id, "user_id" => $user->user_id])) return false;
        $obj->delete();
        return true;
    }
}
