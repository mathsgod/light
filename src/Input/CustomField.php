<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;
use TheCodingMachine\GraphQLite\Undefined;

#[Input(name: "CreateCustomFieldInput", default: true)]
#[Input(name: "UpdateCustomFieldInput")]
class CustomField
{
    #[Field]
    public string|null|Undefined $name = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $label = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $model = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $type = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $placeholder = Undefined::VALUE;

    #[Field]
    /**
     * @var string[]|null
     */
    public ?array $options = null;

    #[Field]
    public string|null|Undefined $validation = Undefined::VALUE;

    #[Field]
    /**
     * @var string[]|null
     */
    public ?array $default_value = null;

    #[Field]
    public int|null|Undefined $order = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $help = Undefined::VALUE;
}
