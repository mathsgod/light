<?php

namespace Light\Framework\Model;

use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "apikey_id", outputType: "Int!")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "key", outputType: "String")]
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "created_time", outputType: "String")]

class APIKey extends \Light\Framework\Model
{
    #[Field]
    public function canDelete(#[InjectUser] ?User $by): bool
    {
        return $by !== null && (int) $this->user_id === (int) $by->user_id;
    }
}
