<?php

namespace Light\Framework\Controller;

use Light\Framework\GraphQL\Annotations\LoadModel;
use TheCodingMachine\GraphQLite\Annotations\Security;
use Light\Framework\Model\SystemValue;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class SystemValueController
{
    #[Query]
    #[Logged]
    /**
     * @return \Light\Framework\Model\SystemValue[]
     * @param ?mixed $filters
     * @deprecated use { app { listSystemvalues }}
     */
    #[Right('systemvalue.list')]
    public function listSystemValue(#[InjectUser] \Light\Framework\Model\User $user, $filters = null,  ?string $sort = ''): \Light\Db\Query
    {
        return SystemValue::Query()->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    #[Right('systemvalue.add')]
    public function addSystemValue(\Light\Framework\Input\SystemValue $data, #[InjectUser] \Light\Framework\Model\User $user): int
    {
        $obj = SystemValue::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->systemvalue_id;
    }

    #[Mutation]
    #[Logged]
    #[Right('systemvalue.update')]
    #[Security('obj.canDelete(user)', failWith: false)]
    public function updateSystemValue(
        #[LoadModel] SystemValue $obj,
        \Light\Framework\Input\SystemValue $data,
    ): bool {
        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right('systemvalue.delete')]
    #[Security('obj.canDelete(user)', failWith: false)]
    public function deleteSystemValue(#[LoadModel] SystemValue $obj): bool
    {
        $obj->delete();
        return true;
    }

    #[Query]
    /**
     * @return mixed
     */
    public function getSystemValue(string $name): array
    {
        $sv = SystemValue::Get(["name" => $name]);
        if (!$sv) return [];
        return $sv->getValues();
    }
}
