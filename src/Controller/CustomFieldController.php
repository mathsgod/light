<?php

namespace Light\Framework\Controller;

use Light\Framework\Model\CustomField;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\UseInputType;


class CustomFieldController
{

    #[Query]
    #[Logged]
    /**
     * @return \Light\Framework\Model\CustomField[]
     * @param ?mixed $filters
     */
    #[Right("customfield.list")]
    public function listCustomField(#[InjectUser] \Light\Framework\Model\User $user, $filters = null,  ?string $sort = ''): \Light\Db\Query
    {
        return CustomField::Query()->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    #[Right("customfield.add")]
    public function addCustomField(\Light\Framework\Input\CustomField $data, #[InjectUser] \Light\Framework\Model\User $user): int
    {
        $obj = CustomField::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->custom_field_id;
    }

    #[Mutation]
    #[Logged]
    #[Right("customfield.update")]
    public function updateCustomField(int $id, #[UseInputType(inputType: "UpdateCustomFieldInput")] \Light\Framework\Input\CustomField $data, #[InjectUser] \Light\Framework\Model\User $user): bool
    {
        if (!$obj = CustomField::Get($id)) return false;
        if (!$obj->canUpdate($user)) return false;
        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right("customfield.delete")]
    public function deleteCustomField(int $id, #[InjectUser] \Light\Framework\Model\User $user): bool
    {
        if (!$obj = CustomField::Get($id)) return false;
        if (!$obj->canDelete($user)) return false;
        $obj->delete();
        return true;
    }
}
