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
    public function fsListFiles(?string $path = "", ?string $type = null, ?string $search = null): array
    {

        $TYPES = [
            "image" => ["jpg", "jpeg", "png", "gif", "svg", "webp", "bmp", "ico"],
            "video" => ["mp4", "webm", "ogg", "avi", "mov", "flv", "wmv", "mkv"],
            "audio" => ["mp3", "wav", "ogg", "m4a", "flac", "aac"],
            "document" => ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "csv", "rtf", "odt", "ods", "odp"],
            "other" => ["zip", "rar", "tar", "gz", "7z", "bz2", "iso", "dmg", "exe", "apk", "torrent"]
        ];


        if ($type !== null) {
            if (!isset($TYPES[$type])) throw new \Exception("Invalid type: $type");

            $files = [];
            foreach ($this->fs->listContents($path, true) as $file) {
                if (!$file->isFile()) continue;
                $path = $file->path();
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                if (!in_array($ext, $TYPES[$type])) continue;

                if ($search !== null) {
                    $filename = basename($path);
                    if (strpos($filename, $search) === false) continue;
                }

                $files[] = new \Light\Type\FS\File($this->fs, $file);
            }
            return $files;
        }

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

    #[Mutation]
    public function fsMoveFile(string $source, string $target): bool
    {
        $basename = basename($source);
        $this->fs->move($source, $target . "/" . $basename);
        return true;
    }
}
