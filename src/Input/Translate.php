<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input]
class Translate
{

    #[Field]
    public string $name;

    #[Field]
    /**
     * @var mixed[]
     */
    public array $values;
}
