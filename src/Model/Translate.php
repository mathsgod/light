<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "translate_id", outputType: "Int!")]
#[MagicField(name: "language", outputType: "String")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "value", outputType: "String")]
class Translate extends \Light\Model
{
}
