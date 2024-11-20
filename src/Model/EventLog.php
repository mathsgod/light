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
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "created_time", outputType: "String!")]
#[MagicField(name: "status", outputType: "Int")]
class EventLog extends \Light\Model
{
    #[Field]
    public function getUsername(): ?string
    {
        if ($u = User::Get($this->user)) {
            return $u->username;
        }
        return null;
    }

    #[Field]
    /**
     * @return mixed
     */
    public function  getSource()
    {
        if (is_string($this->source)) {
            return json_decode($this->source, true);
        }
        return $this->source ?? [];
    }

    #[Field]
    /**
     * @return mixed
     */
    public function  getTarget()
    {
        if (is_string($this->target)) {
            return json_decode($this->target, true);
        }
        return $this->target ?? [];
    }

    #[Field]
    /**
     * @return mixed
     */
    public function getDifferent()
    {
        $source = $this->getSource();
        $target = $this->getTarget();

        $diff = array_diff_assoc($target, $source);
        return $diff;
    }
}
