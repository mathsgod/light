<?php

namespace Light\Model;

use R\DB\Model;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "user_id", outputType: "Int")]
#[MagicField(name: "username", outputType: "String")]
#[MagicField(name: "first_name", outputType: "String")]
#[MagicField(name: "last_name", outputType: "String")]
#[MagicField(name: "email", outputType: "String")]
#[MagicField(name: "phone", outputType: "String")]
class User extends \Light\Model
{

    #[Field]
    /**
     * @return string[]
     */
    public function getRoles(): array
    {

        $q = UserRole::Query(["user_id" => $this->user_id]);
        $roles = [];
        foreach ($q as $r) {
            $roles[] = $r->role;
        }

        return $roles;
    }

    #[Field]
    public function canDelete(): bool
    {
        $roles = $this->getRoles();
        if (in_array("Administrators", $roles)) {
            return false;
        }

        return true;
    }
}
