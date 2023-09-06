<?php

namespace Light\Type\FS;

use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type(name: "FSFile")]
class File
{
    #[Field]
    public string $name;

    protected $file = null;
    protected $fs = null;

    public function __construct(Filesystem $fs, FileAttributes $file)
    {
        $this->fs = $fs;
        $this->file = $file;
        $this->name = $file->path();
    }

    #[Field]
    public function getSize(): int
    {
        return $this->file->fileSize();
    }

    #[Field]
    public function getMime(): string
    {
        return $this->fs->mimeType($this->name);
    }
}
