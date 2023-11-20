<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "userlog_id", outputType: "Int!")]
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "login_dt", outputType: "String!")]
#[MagicField(name: "logout_dt", outputType: "String")]
#[MagicField(name: "ip", outputType: "String")]
#[MagicField(name: "result", outputType: "mixed!")]
#[MagicField(name: "user_agent", outputType: "String")]
#[MagicField(name: "last_access_time", outputType: "String")]

class UserLog extends \Light\Model
{


    #[Field]
    public function getUsername(): ?string
    {
        if ($u = User::Get($this->user)) {
            return $u->username;
        }
    }

    
}
