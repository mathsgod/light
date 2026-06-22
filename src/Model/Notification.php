<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "notification_id", outputType: "Int!")]
#[MagicField(name: "user_id", outputType: "Int!")]
#[MagicField(name: "type", outputType: "String")]
#[MagicField(name: "title", outputType: "String")]
#[MagicField(name: "message", outputType: "String")]
#[MagicField(name: "link", outputType: "String")]
#[MagicField(name: "is_read", outputType: "Int")]
#[MagicField(name: "created_time", outputType: "String")]
class Notification extends \Light\Model
{
    public static bool $_log_insert = false;
    public static bool $_log_delete = false;
    public static bool $_log_update = false;
}
