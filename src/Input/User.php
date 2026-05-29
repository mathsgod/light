<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;
use TheCodingMachine\GraphQLite\Undefined;

#[Input(name: "CreateUserInput", default: true)]
#[Input(name: "UpdateUserInput")]
#[Input(name: "UpdateMyInput")]
class User
{
    // create: required; updateUser: optional; not in updateMy
    #[Field(for: "CreateUserInput", inputType: "String!")]
    #[Field(for: "UpdateUserInput")]
    public string|Undefined $username = Undefined::VALUE;

    // create only
    #[Field(for: "CreateUserInput")]
    public string $password;

    // create: required; updateUser + updateMy: optional
    #[Field(for: "CreateUserInput", inputType: "String!")]
    #[Field(for: "UpdateUserInput")]
    #[Field(for: "UpdateMyInput")]
    public string|Undefined $first_name = Undefined::VALUE;

    // optional in all 3
    #[Field]
    public string|null|Undefined $last_name = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $phone = Undefined::VALUE;

    // create: required; updateUser + updateMy: optional
    #[Field(for: "CreateUserInput", inputType: "String!")]
    #[Field(for: "UpdateUserInput")]
    #[Field(for: "UpdateMyInput")]
    public string|Undefined $email = Undefined::VALUE;

    // optional in all 3
    #[Field]
    public string|null|Undefined $addr1 = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $addr2 = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $addr3 = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $birthdate = Undefined::VALUE;

    // create + updateUser only
    #[Field(for: "CreateUserInput")]
    #[Field(for: "UpdateUserInput")]
    public string|null|Undefined $join_date = Undefined::VALUE;

    #[Field(for: "CreateUserInput")]
    #[Field(for: "UpdateUserInput")]
    public string|null|Undefined $expiry_date = Undefined::VALUE;

    // optional in all 3
    #[Field]
    public string|null|Undefined $default_page = Undefined::VALUE;

    // create + updateUser only
    #[Field(for: "CreateUserInput")]
    #[Field(for: "UpdateUserInput")]
    public int|Undefined $status = Undefined::VALUE;

    // optional in all 3
    #[Field]
    public string|Undefined $language = Undefined::VALUE;

    // create only
    #[Field(for: "CreateUserInput")]
    /**
     * @var string[]
     */
    public array $roles = [];
}
