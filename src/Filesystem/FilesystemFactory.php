<?php

namespace Light\Filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\MountManager;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Light\Model\User;
use RuntimeException;

final class FilesystemFactory
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function createMountManager(array $configs, ?User $user = null): MountManager
    {
        $filesystems = [];

        foreach ($configs as $config) {
            if ($this->requiresAuthenticatedUser($config) && $user === null) {
                continue;
            }

            $name = $config['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new RuntimeException('Filesystem name is required');
            }

            $filesystems[$name] = $this->createFilesystem($config, $user);
        }

        return new MountManager($filesystems);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function createFilesystem(array $config, ?User $user = null): FilesystemOperator
    {
        $config = $this->validateConfig($config);
        $data = $config['data'];
        $adapter = $this->createBaseAdapter($config['type'], $data);
        $resolvedPrefixes = [];

        // Preserve the existing adapter prefix setting while moving prefixing
        // into a generic decorator that works for every adapter type.
        $legacyPrefix = $data['prefix'] ?? '';
        if (is_string($legacyPrefix) && trim($legacyPrefix, '/') !== '') {
            $legacyPrefix = $this->validatePrefix($legacyPrefix);
            $adapter = new PathPrefixedAdapter($adapter, $legacyPrefix);
            $resolvedPrefixes[] = $legacyPrefix;
        }

        foreach ($config['decorators'] as $decorator) {
            if ($decorator['type'] !== 'path_prefix') {
                throw new RuntimeException('Unsupported filesystem decorator: ' . $decorator['type']);
            }

            $prefix = $this->resolvePathPrefix($decorator['data'], $user);
            if ($prefix === '') {
                continue;
            }

            $adapter = new PathPrefixedAdapter($adapter, $prefix);
            $resolvedPrefixes[] = $prefix;
        }

        $filesystemConfig = [];
        if (isset($data['visibility'])) {
            $filesystemConfig['visibility'] = $this->normalizeVisibility($data['visibility']);
        }

        if (!empty($data['public_url']) && is_string($data['public_url'])) {
            $filesystemConfig['public_url'] = $this->scopedPublicUrl(
                $data['public_url'],
                $resolvedPrefixes,
            );
        }

        return new Filesystem($adapter, $filesystemConfig);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function validateConfig(array $config): array
    {
        $name = $config['name'] ?? null;
        if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
            throw new RuntimeException(
                'Filesystem name must start with a letter and contain only letters, numbers, dashes, or underscores'
            );
        }

        $type = $config['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new RuntimeException('Filesystem type is required');
        }

        $data = $config['data'] ?? [];
        if (!is_array($data)) {
            throw new RuntimeException('Filesystem data must be an object');
        }

        $decorators = $config['decorators'] ?? [];
        if (!is_array($decorators)) {
            throw new RuntimeException('Filesystem decorators must be a list');
        }

        $normalizedDecorators = [];
        foreach ($decorators as $decorator) {
            if (!is_array($decorator)) {
                throw new RuntimeException('Filesystem decorator must be an object');
            }

            $decoratorType = $decorator['type'] ?? null;
            if ($decoratorType !== 'path_prefix') {
                throw new RuntimeException('Unsupported filesystem decorator');
            }

            $decoratorData = $decorator['data'] ?? [];
            if (!is_array($decoratorData)) {
                throw new RuntimeException('Filesystem decorator data must be an object');
            }

            $scope = $decoratorData['scope'] ?? 'fixed';
            if (!in_array($scope, ['fixed', 'shared', 'authenticated_user'], true)) {
                throw new RuntimeException('Invalid path prefix scope');
            }

            $prefix = $decoratorData['prefix'] ?? '';
            if (!is_string($prefix)) {
                throw new RuntimeException('Path prefix must be a string');
            }

            if ($prefix !== '') {
                $prefix = $this->validatePrefix($prefix);
            }

            $normalizedDecorators[] = [
                'type' => 'path_prefix',
                'data' => [
                    'prefix' => $prefix,
                    'scope' => $scope,
                ],
            ];
        }

        $config['type'] = $type;
        $config['data'] = $data;
        $config['decorators'] = $normalizedDecorators;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requiresAuthenticatedUser(array $config): bool
    {
        foreach ($config['decorators'] ?? [] as $decorator) {
            $data = is_array($decorator) && is_array($decorator['data'] ?? null)
                ? $decorator['data']
                : [];
            if (
                is_array($decorator)
                && ($decorator['type'] ?? null) === 'path_prefix'
                && ($data['scope'] ?? null) === 'authenticated_user'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createBaseAdapter(string $type, array $data): FilesystemAdapter
    {
        if ($type === 'local') {
            $location = $this->requiredString($data, 'location');
            $visibilityConverter = PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0640,
                    'private' => 0640,
                ],
                'dir' => [
                    'public' => 0777,
                    'private' => 0777,
                ],
            ]);

            return new \League\Flysystem\Local\LocalFilesystemAdapter(
                $location,
                $visibilityConverter,
                lazyRootCreation: true,
            );
        }

        if (in_array($type, ['aliyun-oss', 'oss'], true)) {
            if (!class_exists(\AlphaSnow\Flysystem\Aliyun\AliyunFactory::class)) {
                throw new RuntimeException('Aliyun OSS filesystem adapter is not installed');
            }

            $aliyunData = $data;
            $aliyunData['prefix'] = '';

            return (new \AlphaSnow\Flysystem\Aliyun\AliyunFactory())->createAdapter($aliyunData);
        }

        if ($type === 's3') {
            if (!class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
                throw new RuntimeException('AWS S3 filesystem adapter is not installed');
            }

            $client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $this->requiredString($data, 'region'),
                'endpoint' => $this->requiredString($data, 'endpoint'),
                'use_path_style_endpoint' => $this->booleanValue(
                    $data['use_path_style_endpoint'] ?? true,
                ),
                'credentials' => [
                    'key' => $this->requiredString($data, 'access_key', 'accessKey'),
                    'secret' => $this->requiredString($data, 'secret_key', 'secretKey'),
                ],
            ]);

            return new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $client,
                $this->requiredString($data, 'bucket'),
                '',
                new \League\Flysystem\AwsS3V3\PortableVisibilityConverter(
                    $this->normalizeVisibility($data['visibility'] ?? Visibility::PRIVATE),
                ),
            );
        }

        if ($type === 'hostlink') {
            if (!class_exists(\HL\Storage\Adapter::class)) {
                throw new RuntimeException('Hostlink filesystem adapter is not installed');
            }

            return new \HL\Storage\Adapter(
                $this->requiredString($data, 'token'),
                $this->requiredString($data, 'endpoint'),
            );
        }

        throw new RuntimeException('Filesystem type is not supported: ' . $type);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolvePathPrefix(array $data, ?User $user): string
    {
        $parts = [];
        $prefix = $data['prefix'] ?? '';
        if (is_string($prefix) && $prefix !== '') {
            $parts[] = $this->validatePrefix($prefix);
        }

        if (($data['scope'] ?? 'fixed') === 'authenticated_user') {
            if ($user === null) {
                throw new RuntimeException('Authenticated user is required for this filesystem');
            }
            $parts[] = (string) $user->user_id;
        }

        return implode('/', $parts);
    }

    private function validatePrefix(string $prefix): string
    {
        $prefix = trim($prefix, '/');
        $segments = explode('/', $prefix);
        if (
            $prefix === ''
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || str_contains($prefix, '://')
            || !preg_match('#^[A-Za-z0-9._/-]+$#', $prefix)
        ) {
            throw new RuntimeException('Invalid filesystem path prefix');
        }

        return $prefix;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredString(array $data, string $key, ?string $legacyKey = null): string
    {
        $value = $data[$key] ?? ($legacyKey === null ? null : ($data[$legacyKey] ?? null));
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Filesystem option is required: ' . $key);
        }

        return trim($value);
    }

    private function normalizeVisibility(mixed $visibility): string
    {
        if (!in_array($visibility, [Visibility::PUBLIC, Visibility::PRIVATE], true)) {
            throw new RuntimeException('Filesystem visibility must be public or private');
        }

        return $visibility;
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @param string[] $prefixes
     */
    private function scopedPublicUrl(string $publicUrl, array $prefixes): string
    {
        $segments = array_filter([
            rtrim($publicUrl, '/'),
            ...array_map(static fn (string $prefix): string => trim($prefix, '/'), $prefixes),
        ]);

        return implode('/', $segments) . '/';
    }
}
