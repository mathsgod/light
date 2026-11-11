<?php

namespace Light\Framework\Controller;

use Light\Framework\GraphQL\Annotations\LoadModel;
use TheCodingMachine\GraphQLite\Annotations\Security;
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
    #[Security('obj.canDelete(user)', failWith: false)]
    public function deleteAPIKey(#[LoadModel] APIKey $obj): bool
    {
        $obj->delete();
        return true;
    }
}
