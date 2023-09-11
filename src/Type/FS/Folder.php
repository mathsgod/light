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

    #[Field]
    public string $path;


    protected $dir = null;
    protected $fs = null;

    public function __construct(Filesystem $fs, DirectoryAttributes $dir)
    {
        $this->fs = $fs;
        $this->dir = $dir;
        $this->path =  $dir->path();
        $this->name = basename($dir->path());
    }

    #[Field]
    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        $files = [];
        foreach ($this->fs->listContents($this->path, false) as $file) {
            if (!$file->isFile()) continue;
            $files[] = new File($this->fs, $file);
        }
        return $files;
    }
}
