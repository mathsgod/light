<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input()]
class User
{
    #[Field]
    public string $first_name;

    #[Field]
    public ?string $last_name;

    #[Field]
    public ?string $phone;

    #[Field]
    public string $email;
}
