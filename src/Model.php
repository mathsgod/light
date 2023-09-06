<?php

namespace Light;

use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

abstract class Model extends \R\DB\Model
{
    #[Field]
    public function canDelete(#[InjectUser] ?User $by): bool
    {
        return true;
    }

    #[Field]
    public function canUpdate(#[InjectUser] ?User $by): bool
    {
        return true;
    }

    #[Field]
    public function canRead(#[InjectUser] ?User $by): bool
    {
        return true;
    }
}
