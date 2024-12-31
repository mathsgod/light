<?php

namespace Light\Controller;

use Light\App;
use Light\Model\CustomField;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\UseInputType;


class CustomFieldController
{

    #[Mutation]
    #[Logged]
    #[Right("#Administrators")]
    public function createCustomFieldTable(#[Autowire] App $app): bool
    {
        $sql = "CREATE TABLE `CustomField` (
  `custom_field_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `label` varchar(100) DEFAULT NULL,
  `model` varchar(45) DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `placeholder` varchar(100) DEFAULT NULL,
  `options` json DEFAULT NULL,
  `validation` varchar(100) DEFAULT NULL,
  `default_value` json DEFAULT NULL,
  `order` int(11) DEFAULT '0',
  `help` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`custom_field_id`),
  KEY `model` (`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        )";

        return $app->getDatabase()->exec($sql) ?? false;
    }


    #[Query]
    #[Logged]
    /**
     * @return \Light\Model\CustomField[]
     * @param ?mixed $filters
     */
    #[Right("customfield.list")]
    public function listCustomField(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = ''): \R\DB\Query
    {
        return CustomField::Query()->filters($filters)->sort($sort);
    }

    #[Mutation]
    #[Logged]
    #[Right("customfield.add")]
    public function addCustomField(\Light\Input\CustomField $data, #[InjectUser] \Light\Model\User $user): int
    {
        $obj = CustomField::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->custom_field_id;
    }

    #[Mutation]
    #[Logged]
    #[Right("customfield.update")]
    public function updateCustomField(int $id, #[UseInputType(inputType: "UpdateCustomFieldInput")] \Light\Input\CustomField $data, #[InjectUser] \Light\Model\User $user): bool
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
    public function deleteCustomField(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = CustomField::Get($id)) return false;
        if (!$obj->canDelete($user)) return false;
        $obj->delete();
        return true;
    }
}
