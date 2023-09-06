<?php

namespace Light\Controller;

use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Light\Model\EventLog;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;

class FileSystemController
{

    protected $fs;

    public function __construct()
    {
        $path = getcwd() . "/uploads";
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
        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($path, $visibilityConverter);
        $this->fs = new \League\Flysystem\Filesystem($adapter);
    }

    /*  #[Mutation]
    public function fsMoveFile(string $source, string $target)
    {
    } */


    #[Query]
    /**
     * @return \Light\Type\FS\File[]
     */
    public function fsListFiles(?string $path = ""): array
    {
        $files = [];
        foreach ($this->fs->listContents($path, false) as $file) {
            if (!$file->isFile()) continue;
            $files[] = new \Light\Type\FS\File($this->fs, $file);
        }
        return $files;
    }

    #[Query]
    /**
     * @return \Light\Type\FS\Folder[]
     */
    public function fsListFolders(?string $path = ""): array
    {
        $files = [];
        foreach ($this->fs->listContents($path, false) as $dir) {
            if (!$dir->isDir()) continue;
            $files[] = new \Light\Type\FS\Folder($this->fs, $dir);
        }
        return $files;
    }
}
