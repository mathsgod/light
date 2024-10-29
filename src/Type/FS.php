<?php


namespace Light\Type;

use League\Flysystem\Filesystem;
use Light\Type\FS\File;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class FS
{
    private Filesystem $fs;

    #[Field]
    public string $name;

    public function __construct(string $name, Filesystem $filesystem)
    {
        $this->name = $name;
        $this->fs = $filesystem;
    }


    #[Field]
    public function file(string $path): File
    {
        $list = $this->fs->listContents(dirname($path), false);
        foreach ($list as $file) {
            if ($file->path() === $path) {
                return new \Light\Type\FS\File($this->fs, $file);
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
        foreach ($this->fs->listContents($path, $deep) as $file) {
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

            $files[] = new \Light\Type\FS\File($this->fs, $file);
        }
        return $files;
    }
}
