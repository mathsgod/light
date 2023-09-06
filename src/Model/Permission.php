<?php

namespace Light\Model;

use Laminas\Permissions\Rbac\Role as RbacRole;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "permission_id", outputType: "Int")]
#[MagicField(name: "value", outputType: "String")]
#[MagicField(name: "role", outputType: "String")]
class Permission extends \Light\Model
{
}
