<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input(name: "CreateFileSystemInput", default: true)]
#[Input(name: "UpdateFileSystemInput", update: true)]
class FileSystem
{
    #[Field]
    public ?string $name;

    #[Field]
    public ?string $type;

    #[Field]
    public ?string $location;
}
