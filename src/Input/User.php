<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input(name: "CreateUserInput", default: true)]
#[Input(name: "UpdateUserInput", update: true)]
#[Input(name: "UpdateMyInput", update: true, default: false)]
class User
{
    #[Field(for: "CreateUserInput")]
    #[Field(for: "UpdateUserInput")]
    public string $username;

    #[Field(for: "CreateUserInput")]
    public string $password;

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

    #[Field(for: "CreateUserInput")]
    #[Field(for: "UpdateUserInput")]
    public ?string $join_date;

    #[Field(for: "CreateUserInput")]
    #[Field(for: "UpdateUserInput")]
    public ?string $expiry_date;

    #[Field]
    public ?string $default_page;


    #[Field(for: "CreateUserInput")]
    #[Field(for: "UpdateUserInput")]
    public int $status = 0;

    #[Field]
    public string $language = "en";


    #[Field(for: "CreateUserInput")]
    /**
     * @var string[]
     */
    public array $roles = [];
}
