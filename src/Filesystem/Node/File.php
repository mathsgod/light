<?php

namespace Light\Filesystem\Node;

use League\Flysystem\MountManager;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type()]
class File implements Node
{

    public string $path;
    public function __construct(
        string $path,
        private readonly MountManager $mountManager
    ) {
        $this->path = $path;
    }

    #[Field()]
    public function getName(): string
    {
        return basename($this->path);
    }

    #[Field]
    public function getPath(): string
    {
        return $this->path;
    }

    #[Field]
    public function getSize(): int
    {
        return $this->mountManager->fileSize($this->path);
    }

    #[Field()]
    public function getContent(): string
    {
        return  $this->mountManager->read($this->path);
    }

    #[Field]
    public function getLastModified(): int
    {
        return $this->mountManager->lastModified($this->path);
    }

    #[Field]
    public function getMimetype(): string
    {
        return $this->mountManager->mimeType($this->path);
    }

    #[Field]
    public function getPublicUrl(): ?string
    {
        return $this->mountManager->publicUrl($this->path);
    }
}
