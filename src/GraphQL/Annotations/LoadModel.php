<?php

declare(strict_types=1);

namespace Light\Framework\GraphQL\Annotations;

use Attribute;
use TheCodingMachine\GraphQLite\Annotations\ParameterAnnotationInterface;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class LoadModel implements ParameterAnnotationInterface
{
    public function __construct(public readonly string $id = 'id')
    {
        if (!preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/D', $id)) {
            throw new \InvalidArgumentException('LoadModel requires a valid GraphQL argument name.');
        }
    }
    public function getTarget(): string
    {
        throw new \BadMethodCallException('LoadModel must be used on a parameter.');
    }
}
