<?php

namespace Light\Type;

use Exception;
use Laminas\Permissions\Rbac\Rbac;
use Light\App;
use Light\Model\Config;
use Light\Model\User;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Database
{
    #[Field]
    #[Right("system.database.table")]
    /**
     * @return mixed
     */
    public function getTable(#[Autowire] App $app): array
    {
        $db = $app->getDatabase();
        $tables = $db->getTables();
        $data = [];
        foreach ($tables as $table) {
            $data[] = ["name" => $table->getName(), "columns" => $table->getColumns()];
        }
        return $data;
    }
}
