<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input]
class Role
{
    #[Field]
    public string $name;

    #[Field]
    /**
     * @var string[]
     */
    public array $childs;
}
