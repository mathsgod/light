<?php

declare(strict_types=1);

namespace Light\Framework\GraphQL\Parameters;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Light\Db\Model;
use TheCodingMachine\GraphQLite\Parameters\ParameterInterface;
use TheCodingMachine\GraphQLite\Parameters\ExpandsInputTypeParameters;
use TheCodingMachine\GraphQLite\Parameters\InputTypeParameter;
use TheCodingMachine\GraphQLite\Types\ArgumentResolver;
use TheCodingMachine\GraphQLite\Parameters\MissingArgumentException;

final class LoadModelParameter implements ParameterInterface, ExpandsInputTypeParameters
{
    /** @param class-string<Model> $modelClass */
    public function __construct(private readonly string $name, private readonly string $modelClass)
    {
    }

    public function resolve(?object $source, array $args, mixed $context, ResolveInfo $info): Model
    {
        if (!array_key_exists($this->name, $args)) {
            throw MissingArgumentException::create($this->name);
        }

        $model = $this->modelClass::Get($args[$this->name]);
        if ($model === null) {
            throw new UserError('Record not found.');
        }

        return $model;
    }

    public function toInputTypeParameters(): array
    {
        return [
            $this->name => new InputTypeParameter(
                $this->name,
                Type::nonNull(Type::int()),
                null,
                false,
                null,
                false,
                new ArgumentResolver(),
            ),
        ];
    }
}
