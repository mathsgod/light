<?php

namespace Light\Filesystem\Node;

use League\Flysystem\FileAttributes;
use League\Flysystem\MountManager;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type()]
class File implements Node
{

    public string $location;
    public function __construct(
        string $location,
        private readonly ?array $metadata = null,
    ) {
        $this->location = $location;
    }

    #[Field()]
    public function getName(): string
    {
        return basename($this->location);
    }

    #[Field]
    public function getPath(): string
    {
        $pos = strpos($this->location, '://');
        if ($pos !== false) {
            $path = substr($this->location, $pos + 3);
            // 確保去掉開頭的斜線，保持為 "test/1.txt"
            return ltrim($path, '/');
        }
        return ltrim($this->location, '/');
    }

    #[Field]
    public function getLocation(): string
    {
        return $this->location;
    }

    #[Field]
    public function getSize(#[Autowire] MountManager $mountManager): int
    {
        if (isset($this->metadata['size'])) {
            return $this->metadata['size'];
        }
        return $mountManager->fileSize($this->location);
    }

    #[Field()]
    public function getContent(#[Autowire] MountManager $mountManager): string
    {
        return  $mountManager->read($this->location);
    }

    #[Field]
    public function getLastModified(#[Autowire] MountManager $mountManager): int
    {
        if (isset($this->metadata['last_modified'])) {
            return $this->metadata['last_modified'];
        }
        return $mountManager->lastModified($this->location);
    }

    #[Field]
    public function getMimeType(#[Autowire] MountManager $mountManager): string
    {
        return $mountManager->mimeType($this->location);
    }

    #[Field]
    public function getPublicUrl(#[Autowire] MountManager $mountManager ): ?string
    {
        return $mountManager->publicUrl($this->location);
    }
}
