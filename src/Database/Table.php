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
        return array_map(static fn ($col) => [
            'name' => $col['name'], 'type' => $col['type'],
            'length' => (string)($col['length'] ?? $col['precision_length'] ?? ''),
            'nullable' => (bool)$col['nullable'], 'default' => $col['default'] ?? '',
            'autoincrement' => (bool)$col['auto_increment'],
        ], Sql::columns($this->name));
    }
}
