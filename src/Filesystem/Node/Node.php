<?php

namespace Light\Filesystem\Node;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type()]
interface Node
{
    #[Field()]
    public function getName(): string;

    #[Field]
    public function getPath(): string;

    #[Field]
    public function getLastModified(): int;
}
