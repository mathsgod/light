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

    public function getData()
    {
        if (is_array($this->value)) {
            return $this->value;
        } else {
            $data = [];
            foreach (explode("\n", $this->value) as $s) {
                $s = explode("|", $s, 2);
                $data[is_numeric($s[0]) ? (int) $s[0] : $s[0]] = $s[1] ? trim($s[1]) : $s[0];
            }
            return $data;
        }
    }

    public function getValue($key)
    {
        $data = $this->getData();
        return $data[$key] ?? null;
    }

    public function getValues()
    {

        $values = [];
        foreach ($this->getData() as $k => $v) {
            $values[] = [
                "label" => $v,
                "value" => $k
            ];
        }
        return $values;
    }
}
