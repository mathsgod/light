<?php

namespace Light\Controller;

use Light\Model\SystemValue;
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
     * @return \Light\Model\SystemValue[]
     * @param ?mixed $filters
     */
    #[Right('systemvalue:list')]
    public function listSystemValue(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = ''): \R\DB\Query
    {
        return SystemValue::Query()->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    #[Right('systemvalue:add')]
    public function addSystemValue(\Light\Input\SystemValue $data, #[InjectUser] \Light\Model\User $user): int
    {
        $obj = SystemValue::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->systemvalue_id;
    }

    #[Mutation]
    #[Logged]
    #[Right('systemvalue:update')]
    public function updateSystemValue(int $id,  \Light\Input\SystemValue $data, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = SystemValue::Get($id)) return false;
        if (!$obj->canDelete($user)) return false;
        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right('systemvalue:delete')]
    public function deleteSystemValue(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = SystemValue::Get($id)) return false;
        if (!$obj->canDelete($user)) return false;
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
