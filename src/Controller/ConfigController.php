<?php

namespace Light\Controller;

use Light\Model\Config;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class ConfigController
{
    #[Query]
    #[Right('config.list')]
    #[Logged]
    /**
     * @return \Light\Model\Config[]
     * @param ?mixed $filters
     * @deprecated use { app { configs }}
     */
    public function listConfig(#[InjectUser] \Light\Model\User $user, $filters = [],  ?string $sort = '',): \R\DB\Query
    {
        return Config::Query()->filters($filters)->sort($sort);
    }

    #[Query]
    /**
     * @return \Light\Model\Config[]
     * @deprecated use { app { company companyLogo }}
     */
    public function listConfigBasic(): array
    {
        $q = Config::Query();
        $q->where->in("name", ["company", "company_logo"]);
        return $q->toArray();
    }

    /*   #[Mutation]
    #[Logged]
    public function addConfig(\Input\Config $data, #[InjectUser] \Light\Model\User $user): int
    {
        $obj = Config::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->config_id;
    }

    #[Mutation]
    #[Logged]
    public function updateConfig(int $id,  \Input\Config $data, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Config::Get($id)) return false;
        $obj->bind($data);
        $obj->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    public function removeConfig(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Config::Get($id)) return false;
        $obj->delete();
        return true;
    } */
}
