<?php

namespace Light\Type;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
final class UserIdentity
{
    public function __construct(
        private readonly int $user_id,
        private readonly string $name,
    ) {}

    #[Field(name: 'user_id')]
    public function getUserId(): int
    {
        return $this->user_id;
    }

    #[Field]
    public function getName(): string
    {
        return $this->name;
    }
}
