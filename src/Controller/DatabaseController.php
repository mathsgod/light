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
     * @param mixed $columns
     */
    public function createTable(#[Autowire] App $app, string $table, array $columns): bool
    {
        /*         $db = $app->getDatabase();



        $t=new CreateTable($table);
        $t->addColumn(new Column\Integer("id"));
        $t->addColumn(new Column\Varchar("name"));


        $db->createTable($table, $columns);
 */
        return false;
    }
}
