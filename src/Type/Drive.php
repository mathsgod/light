<?php

namespace Light\Type;

use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use Light\Type\FS\File;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Drive
{

    #[Field]
    public string $name;
    #[Field]
    public int $index;

    protected Filesystem $filesystem;
    private array $data;

    public function __construct(string $name, Filesystem $filesystem, int $index, array $data = [])
    {
        $this->name = $name;
        $this->filesystem = $filesystem;
        $this->index = $index;
        $this->data = $data;
    }

    public function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    public function getFileUrl(string $path): string
    {
        if ($this->data["url"]) {
            return $this->data["url"] . $path;
        }

        return "/api/drive/$this->index/$path";
    }


    #[Field]
    public function file(string $path): ?File
    {
        $list = $this->filesystem->listContents(dirname($path), false);
        foreach ($list as $file) {
            if ($file->path() === $path) {
                return new \Light\Type\FS\File($this, $file);
            }
        }
        return null;
    }

    #[Field]
    /**
     * @return \Light\Type\FS\File[]
     */
    #[Right('fs.file.list')]
    public function getFiles(?string $path = "", ?string $type = null, ?string $search = null): array
    {

        $TYPES = [
            "image" => ["jpg", "jpeg", "png", "gif", "svg", "webp", "bmp", "ico"],
            "video" => ["mp4", "webm", "ogg", "avi", "mov", "flv", "wmv", "mkv"],
            "audio" => ["mp3", "wav", "ogg", "m4a", "flac", "aac"],
            "document" => ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "csv", "rtf", "odt", "ods", "odp"],
            "other" => ["zip", "rar", "tar", "gz", "7z", "bz2", "iso", "dmg", "exe", "apk", "torrent"]
        ];

        $deep = false;
        if ($search !== null || $type !== null) $deep = true;

        if ($type !== null) {
            if (!isset($TYPES[$type])) throw new \Exception("Invalid type: $type");
        }

        $files = [];
        foreach ($this->filesystem->listContents($path, $deep) as $file) {
            if (!$file->isFile()) continue;
            $path = $file->path();
            $ext = pathinfo($path, PATHINFO_EXTENSION);

            if ($type !== null) {
                if (!in_array($ext, $TYPES[$type])) continue;
            }


            if ($search !== null) {
                $filename = basename($path);
                if (strpos($filename, $search) === false) continue;
            }

            $files[] = new \Light\Type\FS\File($this, $file);
        }
        return $files;
    }

    #[Field]
    /**
     * @return \Light\Type\FS\Folder[]
     */
    #[Right('fs.folder.list')]
    public function folders(?string $path = ""): array
    {
        $files = [];
        foreach ($this->filesystem->listContents($path, false) as $dir) {
            if (!$dir->isDir()) continue;
            $files[] = new \Light\Type\FS\Folder($this, $dir);
        }
        return $files;
    }


    #[Field]
    public function folder(string $path): ?\Light\Type\FS\Folder
    {
        $list = $this->filesystem->listContents(dirname($path), false);
        foreach ($list as $dir) {
            if (!$dir->isDir()) continue;
            if ($dir->path() === $path)
                return new \Light\Type\FS\Folder($this, $dir);
        }
        return null;
    }
}