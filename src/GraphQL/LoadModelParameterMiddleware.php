<?php

declare(strict_types=1);

namespace Light\Framework\GraphQL;

use Light\Db\Model;
use Light\Framework\GraphQL\Annotations\LoadModel;
use Light\Framework\GraphQL\Parameters\LoadModelParameter;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\Type;
use ReflectionNamedType;
use ReflectionParameter;
use TheCodingMachine\GraphQLite\Annotations\ParameterAnnotations;
use TheCodingMachine\GraphQLite\Mappers\Parameters\ParameterHandlerInterface;
use TheCodingMachine\GraphQLite\Mappers\Parameters\ParameterMiddlewareInterface;
use TheCodingMachine\GraphQLite\Parameters\ParameterInterface;

final class LoadModelParameterMiddleware implements ParameterMiddlewareInterface
{
    public function mapParameter(ReflectionParameter $parameter, DocBlock $docBlock, ?Type $paramTagType, ParameterAnnotations $parameterAnnotations, ParameterHandlerInterface $next): ParameterInterface
    {
        $attribute = $parameterAnnotations->getAnnotationByType(LoadModel::class);
        if ($attribute === null) {
            return $next->mapParameter($parameter, $docBlock, $paramTagType, $parameterAnnotations);
        }

        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->allowsNull() || !is_subclass_of($type->getName(), Model::class) || $parameter->isOptional() || $parameter->isVariadic()) {
            throw new \InvalidArgumentException('LoadModel requires a required, non-nullable Light model parameter.');
        }

        return new LoadModelParameter($attribute->id, $type->getName());
    }
}
