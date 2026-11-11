<?php

declare(strict_types=1);

namespace Light\Framework\GraphQL;

use Closure;
use GraphQL\Type\Definition\FieldDefinition;
use Light\Framework\GraphQL\Parameters\LoadModelParameter;
use TheCodingMachine\GraphQLite\Middlewares\AuthorizationFieldMiddleware;
use TheCodingMachine\GraphQLite\Middlewares\FieldHandlerInterface;
use TheCodingMachine\GraphQLite\Middlewares\FieldMiddlewareInterface;
use TheCodingMachine\GraphQLite\QueryFieldDescriptor;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;
use TheCodingMachine\GraphQLite\Security\AuthorizationServiceInterface;

/**
 * Applies GraphQLite's authorization before resolving LoadModel parameters.
 */
final class LoadModelAuthorizationMiddleware implements FieldMiddlewareInterface
{
    /**
     * @param Closure(): (AuthenticationServiceInterface&AuthorizationServiceInterface) $getAuthService
     */
    public function __construct(private readonly Closure $getAuthService)
    {
    }

    public function process(QueryFieldDescriptor $queryFieldDescriptor, FieldHandlerInterface $fieldHandler): ?FieldDefinition
    {
        $hasLoadModel = false;
        foreach ($queryFieldDescriptor->getParameters() as $parameter) {
            if ($parameter instanceof LoadModelParameter) {
                $hasLoadModel = true;
                break;
            }
        }

        $field = $fieldHandler->handle($queryFieldDescriptor);
        if (!$hasLoadModel || $field === null) {
            return $field;
        }

        // Wrap the completed GraphQL resolver, outside parameter resolution.
        $descriptor = $queryFieldDescriptor->withResolver($field->resolveFn);
        $authService = ($this->getAuthService)();
        $authorization = new AuthorizationFieldMiddleware($authService, $authService);

        return $authorization->process($descriptor, new class($field) implements FieldHandlerInterface {
            public function __construct(private readonly FieldDefinition $field)
            {
            }

            public function handle(QueryFieldDescriptor $descriptor): ?FieldDefinition
            {
                $field = clone $this->field;
                $field->resolveFn = $descriptor->getResolver();

                return $field;
            }
        });
    }
}
