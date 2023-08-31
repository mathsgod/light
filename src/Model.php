<?php

namespace Light;

use TheCodingMachine\GraphQLite\Annotations\Field;

abstract class Model extends \R\DB\Model
{
    #[Field]
    public function canDelete(): bool
    {
        return true;
    }

    #[Field]
    public function canUpdate(): bool
    {
        return true;
    }

    #[Field]
    public function canRead(): bool
    {
        return true;
    }
}
