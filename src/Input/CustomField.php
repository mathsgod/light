<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input(name: "CreateCustomFieldInput", default: true)]
#[Input(name: "UpdateCustomFieldInput", update: true)]
class CustomField
{
    #[Field]
    public ?string $name;

    #[Field]
    public ?string $label;

    #[Field]
    public ?string $model;

    #[Field]
    public ?string $type;

    #[Field]
    public ?string $placeholder;

    #[Field]
    /**
     * @var string[]
     */
    public $options;

    #[Field]
    public ?string $validation;

    #[Field]
    public ?string $default_value;

    #[Field]
    public ?int $order;

    #[Field]
    public ?string $help;

}
