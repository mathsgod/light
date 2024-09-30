<?php

namespace Light\Controller;

use Firebase\JWT\JWT;
use Laminas\Db\Sql\Ddl\CreateTable;
use Light\App;
use Light\Type\System;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use Laminas\Db\Sql\Ddl\Column;

class DatabaseController
{
    #[Mutation]
    #[Right("system.database.table.create")]
    /**
     * @param \Light\Input\Table\Column[] $columns
     */
    public function createDatabaseTable(#[Autowire] App $app, string $name, array $columns): bool
    {
        $db = $app->getDatabase();

        $t = new CreateTable($name);
        foreach ($columns as $column) {
            if ($column->type == "int") {
                $t->addColumn(new Column\Integer($column->name, $column->nullable ?? false));
            }
        }

        $db->exec($t->getSqlString($db->getAdapter()->getPlatform()));



        return false;
    }
}
