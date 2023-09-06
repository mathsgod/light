<?php

namespace Light\Type\FS;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type(name: "FSFolder")]
class Folder
{
    #[Field]
    public string $name;

    protected $dir = null;
    protected $fs = null;

    public function __construct(Filesystem $fs, DirectoryAttributes $dir)
    {
        $this->fs = $fs;
        $this->dir = $dir;
        $this->name = $dir->path();
    }
}
