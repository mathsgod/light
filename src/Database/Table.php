<?php

namespace Light\Database;

use Light\App;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Table
{

    public function __construct(private string $name, private array $status) {}

    #[Field]
    public function getName(): string
    {
        return $this->name;
    }

    #[Field(outputType: "mixed")]
    public function getStatus(): array
    {
        // convert key to lowercase
        return array_change_key_case($this->status, CASE_LOWER);
    }

    #[Field(outputType: "mixed")]
    public function getColumns(#[Autowire()] App $app): array
    {
        $db = $app->getDatabase();
        $table = $db->getTable($this->name);
        return $table->getColumns();
    }
}
