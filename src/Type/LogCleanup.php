<?php

namespace Light\Type;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class LogCleanup
{
    public function __construct(
        private bool $enabled,
        private int $days
    ) {}

    #[Field]
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    #[Field]
    public function getDays(): int
    {
        return $this->days;
    }
}
