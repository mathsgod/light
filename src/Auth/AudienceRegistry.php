<?php

namespace Light\Auth;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class AudienceRegistry
{
    /** @var array<string, array{permissions: string[]}> */
    private array $audiences;

    public function __construct(?string $path = null)
    {
        $path ??= dirname(__DIR__, 2) . '/audiences.yml';
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Audience configuration is not readable: {$path}");
        }

        $config = Yaml::parseFile($path);
        if (!is_array($config)) {
            throw new RuntimeException('audiences.yml must contain an audience map');
        }

        $this->audiences = [];
        foreach ($config as $audience => $settings) {
            if (!is_string($audience) || $audience === '') {
                throw new RuntimeException('Audience names must be non-empty strings');
            }
            if (!is_array($settings)) {
                throw new RuntimeException("Audience '{$audience}' must contain a settings map");
            }

            $permissions = $settings['permissions'] ?? [];
            if (!is_array($permissions)) {
                throw new RuntimeException("Audience '{$audience}' permissions must be a list");
            }
            foreach ($permissions as $permission) {
                if (!is_string($permission) || $permission === '') {
                    throw new RuntimeException("Audience '{$audience}' contains an invalid permission");
                }
            }

            $this->audiences[$audience] = [
                'permissions' => array_values(array_unique($permissions)),
            ];
        }
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->audiences);
    }

    /** @return string[] */
    public function permissions(string $audience): array
    {
        if (!isset($this->audiences[$audience])) {
            throw new InvalidArgumentException("Unknown JWT audience: {$audience}");
        }
        return $this->audiences[$audience]['permissions'];
    }

    /**
     * @param string[] $userPermissions
     * @return string[]
     */
    public function filterPermissions(string $audience, array $userPermissions): array
    {
        $allowed = $this->permissions($audience);
        $granted = array_values(array_filter(
            $userPermissions,
            static fn(string $permission): bool => $permission !== ''
                && !str_starts_with($permission, '#')
        ));

        $result = [];
        foreach ($allowed as $allowedPattern) {
            foreach ($granted as $grantedPattern) {
                if (self::covers($grantedPattern, $allowedPattern)) {
                    $result[] = $allowedPattern;
                } elseif (self::covers($allowedPattern, $grantedPattern)) {
                    $result[] = $grantedPattern;
                }
            }
        }

        $result = array_values(array_unique($result));
        sort($result);
        return $result;
    }

    private static function covers(string $pattern, string $permission): bool
    {
        if ($pattern === '*' || $pattern === $permission) {
            return true;
        }
        if (!str_ends_with($pattern, '.*')) {
            return false;
        }

        $prefix = substr($pattern, 0, -1);
        return str_starts_with($permission, $prefix);
    }
}
