<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "eventlog_id", outputType: "Int!")]
#[MagicField(name: "class", outputType: "String")]
#[MagicField(name: "id", outputType: "Int!")]
#[MagicField(name: "action", outputType: "String")]
#[MagicField(name: "source", outputType: "mixed")]
#[MagicField(name: "target", outputType: "mixed")]
#[MagicField(name: "remark", outputType: "String")]
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "created_time", outputType: "String!")]
#[MagicField(name: "status", outputType: "Int")]
#[MagicField(name: "different", outputType: "mixed")]
class EventLog extends \Light\Model
{
    #[Field]
    public function getUsername(): ?string
    {
        if ($u = User::Get($this->user)) {
            return $u->username;
        }
    }
}
