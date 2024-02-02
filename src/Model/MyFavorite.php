<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "my_favorite_id", outputType: "Int!")]
#[MagicField(name: "path", outputType: "String")]
#[MagicField(name: "label", outputType: "String")]
#[MagicField(name: "icon", outputType: "String")]

class MyFavorite extends \Light\Model
{
}
