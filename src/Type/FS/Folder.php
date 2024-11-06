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
    protected $drive = null;

    public function __construct(\Light\Type\Drive $drive, DirectoryAttributes $dir)
    {
        $this->drive = $drive;
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
        foreach ($this->drive->getFilesystem()->listContents($this->path, false) as $file) {
            if (!$file->isFile()) continue;
            $files[] = new File($this->drive, $file);
        }
        return $files;
    }

    #[Field]
    public function getTotalSize(): int
    {
        $size = 0;
        foreach ($this->drive->getFilesystem()->listContents($this->path, true) as $file) {
            if ($file instanceof FileAttributes) {
                $size += intval($file->fileSize());
            }
        }
        return $size;
    }
}
