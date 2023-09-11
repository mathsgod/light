<?php

namespace Light\Controller;

use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Light\Model\EventLog;
use Psr\Http\Message\UploadedFileInterface;
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
    #[Right('fs.listFiles')]
    public function fsListFiles(?string $path = "", ?string $type = null, ?string $search = null): array
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

    #[Query]
    /**
     * @return \Light\Type\FS\Folder[]
     */
    #[Right('fs.listFolders')]
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
    #[Right("fs.folder.create")]
    public function fsCreateFolder(string $path): bool
    {
        $this->fs->createDirectory($path);
        return true;
    }

    #[Mutation]
    public function fsDeleteFolder(string $path): bool
    {
        $this->fs->deleteDirectory($path);
        return true;
    }

    #[Mutation]
    public function fsDeleteFile(string $path): bool
    {
        $this->fs->delete($path);
        return true;
    }

    #[Mutation]
    public function fsRenameFile(string $path, string $name): bool
    {
        $this->fs->move($path, dirname($path) . "/" . $name);
        return true;
    }

    #[Mutation]
    public function fsRenameFolder(string $path, string $name): bool
    {
        $this->fs->move($path, dirname($path) . "/" . $name);
        return true;
    }

    #[Mutation]
    public function fsUploadFile(string $path, UploadedFileInterface $file): bool
    {
        $this->fs->write($path, $file->getStream()->getContents());
        return true;
    }

    #[Right('fs.moveFile')]
    public function fsMoveFile(string $source, string $target): bool
    {
        $basename = basename($source);
        $this->fs->move($source, $target . "/" . $basename);
        return true;
    }
}
