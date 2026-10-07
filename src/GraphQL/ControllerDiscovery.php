<?php
declare(strict_types=1);

namespace Light\GraphQL;

use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\DefinitionContainerInterface;
use League\Container\Exception\NotFoundException;
use ReflectionClass;

final class ControllerDiscovery
{
    public static function finder(DefinitionContainerInterface $container): ComposerFinder
    {
        return (new ComposerFinder())->filter(static function (ReflectionClass $class) use ($container): bool {
            if (!$class->implementsInterface(ExplicitController::class)) {
                return true;
            }

            // has() includes reflection autowiring, so check actual definitions.
            try {
                $container->extend($class->getName());
                return true;
            } catch (NotFoundException) {
                return false;
            }
        });
    }
}
