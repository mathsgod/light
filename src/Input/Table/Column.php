<?php

namespace Light\Input\Table;

use Psr\Http\Message\UploadedFileInterface;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input]
class Column
{
    #[Field]
    public string $name;

    #[Field]
    public string $type;

    #[Field]
    public ?bool $nullable;

    #[Field]
    public ?bool $auto_increment;
}
