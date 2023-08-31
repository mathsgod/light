<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "user_role_id", outputType: "Int!")]
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "role", outputType: "String")]
class UserRole extends \Light\Model
{

    
}
