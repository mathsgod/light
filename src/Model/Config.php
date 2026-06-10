<?php

namespace Light\Model;

use Light\App;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "config_id", outputType: "Int!")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "value", outputType: "String")]
class Config extends \Light\Model
{
    private const CACHE_KEY_PREFIX = "config_value_";
    private const CACHE_TTL = 60;

    public static function Value(string $name, ?string $default = null): ?string
    {
        $cache = self::getCache();
        $cacheKey = self::CACHE_KEY_PREFIX . $name;

        if ($cache !== null && $cache->has($cacheKey)) {
            return $cache->get($cacheKey);
        }

        $config = self::Get(["name" => $name]);
        if ($config) {
            $value = (is_null($config->value) || $config->value === '') ? $default : $config->value;
        } else {
            $value = $default;
        }

        if ($cache !== null) {
            $cache->set($cacheKey, $value, self::CACHE_TTL);
        }

        return $value;
    }

    /**
     * Invalidate the cached value for a single config key.
     * Call this after Config::Create / save() / delete() if the new
     * value needs to be visible immediately rather than after
     * CACHE_TTL seconds.
     */
    public static function Invalidate(string $name): void
    {
        $cache = self::getCache();
        if ($cache !== null) {
            $cache->delete(self::CACHE_KEY_PREFIX . $name);
        }
    }

    private static function getCache(): ?\Psr\SimpleCache\CacheInterface
    {
        $container = \Light\Model::$container ?? null;
        if ($container === null) {
            return null;
        }
        try {
            $app = $container->get(App::class);
            return $app instanceof App ? $app->getCache() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
