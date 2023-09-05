<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "maillog_id", outputType: "Int!")]
#[MagicField(name: "created_time", outputType: "String")]
#[MagicField(name: "from", outputType: "String")]
#[MagicField(name: "to", outputType: "String")]
#[MagicField(name: "body", outputType: "String")]
#[MagicField(name: "subject", outputType: "String")]
#[MagicField(name: "from_name", outputType: "String")]
#[MagicField(name: "to_name", outputType: "String")]
#[MagicField(name: "altbody", outputType: "String")]
#[MagicField(name: "host", outputType: "String")]
class MailLog extends \Light\Model
{
}
