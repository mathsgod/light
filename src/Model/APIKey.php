<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "apikey_id", outputType: "Int!")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "key", outputType: "String")]
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "created_time", outputType: "String")]

class APIKey extends \Light\Model
{
}
