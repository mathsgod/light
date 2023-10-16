<?php

namespace Light\Controller;

use GraphQL\Error\Error;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Light\Model\EventLog;
use Light\Type\FS\File;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;

class FileSystemController
{
    const DISALLOW_EXT = ['zip', 'js', 'jsp', 'jsb', 'mhtml', 'mht', 'xhtml', 'xht', 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'shtml', 'jhtml', 'pl', 'sh', 'py', 'cgi', 'exe', 'application', 'gadget', 'hta', 'cpl', 'msc', 'jar', 'vb', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps2', 'psc1', 'psc2', 'msh', 'msh1', 'msh2', 'inf', 'reg', 'scf', 'msp', 'scr', 'dll', 'msi', 'vbs', 'bat', 'com', 'pif', 'cmd', 'vxd', 'cpl', 'htpasswd', 'htaccess'];

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

    #[Mutation]
    #[Right("fs.file.write")]
    public function fsWriteFileBase64(string $path, string $content): bool
    {
        $this->fs->write($path, base64_decode($content));
        return true;
    }

    #[Mutation]
    #[Right("fs.file.upload")]
    public function fsUploadTempFile(UploadedFileInterface $file): File
    {
        //get path extension
        $filename = $file->getClientFilename();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        //check if extension is allowed
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        //random name
        $filename = UUID::uuid4()->toString() . "." . $ext;

        //move file
        $this->fs->write("temp/" . $filename, $file->getStream()->getContents());

        $list = $this->fs->listContents("temp", false);
        foreach ($list as $file) {
            if ($file->path() === "temp/" . $filename) {
                return new File($this->fs, $file);
            }
        }

        throw new Error("File not found");
    }

    /*  #[Mutation]
    public function fsMoveFile(string $source, string $target)
    {
    } */

    #[Query]
    public function fsFile(string $path): File
    {

        $list = $this->fs->listContents(dirname($path), false);
        foreach ($list as $file) {
            if ($file->path() === $path) {
                return new \Light\Type\FS\File($this->fs, $file);
            }
        }
        return null;
    }




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
    #[Right("fs.folder.delete")]
    public function fsDeleteFolder(string $path): bool
    {
        $this->fs->deleteDirectory($path);
        return true;
    }

    #[Mutation]
    #[Right("fs.file.delete")]
    public function fsDeleteFile(string $path): bool
    {
        $this->fs->delete($path);
        return true;
    }

    #[Mutation]
    #[Right("fs.file.rename")]
    public function fsRenameFile(string $path, string $name): bool
    {

        //check extension
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        $this->fs->move($path, dirname($path) . "/" . $name);
        return true;
    }

    #[Mutation]
    #[Right("fs.folder.rename")]
    public function fsRenameFolder(string $path, string $name): bool
    {
        $this->fs->move($path, dirname($path) . "/" . $name);
        return true;
    }

    #[Mutation]
    #[Right("fs.move")]
    public function fsMove(string $path, string $target): bool
    {
        if ($this->fs->fileExists($path)) {
            $this->fs->move($path, $target . "/" . basename($path));
            return true;
        }

        if ($this->fs->directoryExists($path)) {
            $this->fs->move($path, $target . "/" . basename($path));
            return true;
        }

        return false;
    }

    #[Mutation]
    #[Right("fs.file.upload")]
    public function fsUploadFile(string $path, UploadedFileInterface $file): bool
    {
        //get path extension
        $filename = $file->getClientFilename();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        //check if extension is allowed
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        //check if file already exists
        if ($this->fs->fileExists($path . "/" . $filename)) throw new Error("File already exists");

        //move file
        $this->fs->write($path . "/" . $filename, $file->getStream()->getContents());
        return true;
    }

    #[Right('fs.file.move')]
    public function fsMoveFile(string $source, string $target): bool
    {
        $basename = basename($source);
        $this->fs->move($source, $target . "/" . $basename);
        return true;
    }
}
