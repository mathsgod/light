<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "config_id", outputType: "Int!")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "value", outputType: "String")]
class Config extends \Light\Model
{
    public static function Value(string $name, ?string $default = ""): string
    {
        $config = self::Get(["name" => $name]);
        if ($config) {
            return $config->value ?? $default ?? "";
        }
        return $default ?? "";
    }
}
