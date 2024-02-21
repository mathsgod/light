<?php

namespace Light\Type\FS;

use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type(name: "FSFile")]
class File
{
    #[Field]
    public string $name;

    #[Field]
    public string $path;


    protected $file = null;
    protected $fs = null;

    public function __construct(Filesystem $fs, FileAttributes $file)
    {
        $this->fs = $fs;
        $this->file = $file;
        $this->path = $file->path();
        $this->name = basename($file->path());
    }

    #[Field]
    public function getLastModifiedHuman(): string
    {
        return date("Y-m-d H:i:s", $this->file->lastModified());
    }

    #[Field]
    public function getLastModified(): int
    {
        return $this->file->lastModified();
    }

    #[Field]
    public function getSize(): int
    {
        return $this->file->fileSize();
    }

    #[Field]
    public function getMime(): string
    {
        return $this->fs->mimeType($this->path);
    }

    #[Field]
    public function canPreview(): bool
    {
        if ($this->getMime() != "image/jpeg") {
            return false;
        }
        return true;
    }

    #[Field]
    public function getImagePath(): string
    {
        return "/api/uploads/$this->path";
    }

    #[Field]
    public function getBase64Content(): string
    {
        return base64_encode($this->fs->read($this->path));
    }
}
