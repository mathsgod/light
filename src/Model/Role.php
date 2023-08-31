<?php

namespace Light\Model;

use R\DB\Model;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "role_id", outputType: "Int")]
#[MagicField(name: "name", outputType: "String")]
class Role extends Model
{
}
