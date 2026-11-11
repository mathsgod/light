<?php

namespace Light\Framework\Model;

use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "my_favorite_id", outputType: "Int!")]
#[MagicField(name: "path", outputType: "String")]
#[MagicField(name: "label", outputType: "String")]
#[MagicField(name: "icon", outputType: "String")]
#[MagicField(name: "sequence", outputType: "Int")]
#[MagicField(name: "user_id", outputType: "Int")]



class MyFavorite extends \Light\Framework\Model
{
    #[Field]
    public function canUpdate(#[InjectUser] ?User $by): bool
    {
        return $by !== null && (int) $this->user_id === (int) $by->user_id;
    }
}
