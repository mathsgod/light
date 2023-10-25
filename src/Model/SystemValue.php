<?php

namespace Light\Model;

use Light\Model;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "systemvalue_id", outputType: "Int!")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "value", outputType: "String")]
class SystemValue extends Model
{

    public function getValues()
    {
        $values = [];
        if (is_array($this->value)) {
            foreach ($this->value as $k => $v) {
                $values[] = [
                    "label" => $v,
                    "value" => $k
                ];
            }
        } else {
            //explode by enter
            foreach (explode("\n", $this->value) as $s) {

                //split by |
                $s = explode("|", $s, 2);

                $values[] = [
                    "label" => trim($s[1]),
                    "value" => is_numeric($s[0]) ? (int) $s[0] : $s[0]
                ];
            }
        }

        return $values;
    }
}
