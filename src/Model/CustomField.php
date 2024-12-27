<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "custom_field_id", outputType: "Int!")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "label", outputType: "String")]
#[MagicField(name: "model", outputType: "String")]
#[MagicField(name: "type", outputType: "String")]
#[MagicField(name: "placeholder", outputType: "String")]
#[MagicField(name: "options", outputType: "String")]
#[MagicField(name: "required", outputType: "Boolean")]
#[MagicField(name: "default_value", outputType: "String")]
#[MagicField(name: "order", outputType: "Int")]
class CustomField extends \Light\Model {}
