<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input()]
class User
{
    #[Field]
    public string $username;

    #[Field]
    public string $first_name;

    #[Field]
    public ?string $last_name;

    #[Field]
    public ?string $phone;

    #[Field]
    public string $email;

    #[Field]
    public ?string $addr1;

    #[Field]
    public ?string $addr2;

    #[Field]
    public ?string $addr3;

    #[Field]
    public ?string $birthdate;

    #[Field]
    public string $join_date;
    

    #[Field]
    /**
     * @var string[]
     */
    public array $roles = [];
}
