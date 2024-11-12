<?php

namespace Light\Controller;

use GraphQL\Error\Error;
use Light\Drive\Drive;
use Light\Drive\File;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;

class FileManagerController
{
    const DISALLOW_EXT = ['zip', 'js', 'jsp', 'jsb', 'mhtml', 'mht', 'xhtml', 'xht', 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'shtml', 'jhtml', 'pl', 'sh', 'py', 'cgi', 'exe', 'application', 'gadget', 'hta', 'cpl', 'msc', 'jar', 'vb', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps2', 'psc1', 'psc2', 'msh', 'msh1', 'msh2', 'inf', 'reg', 'scf', 'msp', 'scr', 'dll', 'msi', 'vbs', 'bat', 'com', 'pif', 'cmd', 'vxd', 'cpl', 'htpasswd', 'htaccess'];

    protected $drive;

    public function __construct(Drive $drive)
    {
        $this->drive = $drive;
    }

    #[Mutation]
    #[Right("fs.file.write")]
    public function fsWriteFileBase64(string $path, string $content): bool
    {
        $this->drive->getFilesystem()->write($path, base64_decode($content));
        return true;
    }


    #[Mutation]
    #[Right("fs.file.write")]
    /**
     * @deprecated use lightDriveWriteFile
     */
    public function fsWriteFile(string $path, string $content): bool
    {
        $this->drive->getFilesystem()->write($path, $content);
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
        $this->drive->getFilesystem()->write("temp/" . $filename, $file->getStream()->getContents());

        $list = $this->drive->getFilesystem()->listContents("temp", false);
        foreach ($list as $file) {
            if ($file->path() === "temp/" . $filename) {
                return new File($this->drive, $file);
            }
        }

        throw new Error("File not found");
    }


    #[Query]
    /**
     * @deprecated use app { drive { file } }
     */
    public function fsFile(string $path): File
    {

        $list = $this->drive->getFilesystem()->listContents(dirname($path), false);
        foreach ($list as $file) {
            if ($file->path() === $path) {
                return new \Light\Drive\File($this->drive, $file);
            }
        }
        return null;
    }

    #[Query]
    /**
     * @return \Light\Drive\File[]
     * @deprecated use app { drive { files } }
     */
    #[Right('fs.file.list')]
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
        foreach ($this->drive->getFilesystem()->listContents($path, $deep) as $file) {
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

            $files[] = new \Light\Drive\File($this->drive, $file);
        }
        return $files;
    }

    #[Query]
    /**
     * @return \Light\Drive\Folder[]
     * @deprecated use app { drive { folders } }
     */
    #[Right('fs.folder.list')]
    public function fsListFolders(?string $path = ""): array
    {
        $files = [];
        foreach ($this->drive->getFilesystem()->listContents($path, false) as $dir) {
            if (!$dir->isDir()) continue;
            $files[] = new \Light\Drive\Folder($this->drive, $dir);
        }
        return $files;
    }

    #[Mutation]
    #[Right("fs.folder.create")]
    /**
     * @deprecated use lightDriveCreateFolder
     */
    public function fsCreateFolder(string $path): bool
    {
        $this->drive->getFilesystem()->createDirectory($path);
        return true;
    }

    #[Mutation]
    #[Right("fs.folder.delete")]
    /**
     * @deprecated use lightDriveDeleteFolder
     */
    public function fsDeleteFolder(string $path): bool
    {
        $this->drive->getFilesystem()->deleteDirectory($path);
        return true;
    }

    #[Mutation]
    #[Right("fs.file.delete")]
    /**
     * @deprecated use lightDriveDeleteFile
     */
    public function fsDeleteFile(string $path): bool
    {
        $this->drive->getFilesystem()->delete($path);
        return true;
    }

    #[Mutation]
    #[Right("fs.file.rename")]
    /**
     * @deprecated use lightDriveRenameFile
     */
    public function fsRenameFile(string $path, string $name): bool
    {

        //check extension
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        $this->drive->getFilesystem()->move($path, dirname($path) . "/" . $name);
        return true;
    }

    #[Mutation]
    #[Right("fs.folder.rename")]
    /**
     * @deprecated use lightDriveRenameFolder
     */
    public function fsRenameFolder(string $path, string $name): bool
    {
        $this->drive->getFilesystem()->move($path, dirname($path) . "/" . $name);
        return true;
    }

    #[Mutation]
    #[Right("fs.move")]
    public function fsMove(string $path, string $target): bool
    {
        if ($this->drive->getFilesystem()->fileExists($path)) {
            $this->drive->getFilesystem()->move($path, $target . "/" . basename($path));
            return true;
        }

        if ($this->drive->getFilesystem()->directoryExists($path)) {
            $this->drive->getFilesystem()->move($path, $target . "/" . basename($path));
            return true;
        }

        return false;
    }

    private function getNextFilename($path, $filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = pathinfo($filename, PATHINFO_FILENAME);

        $i = 1;
        while ($this->drive->getFilesystem()->fileExists($path . "/" . $filename . "($i)." . $ext)) {
            $i++;
        }
        return $filename . "($i)." . $ext;
    }

    #[Mutation]
    #[Right("fs.file.upload")]
    /**
     * @deprecated use lightDriveUploadFile
     */
    public function fsUploadFile(string $path, UploadedFileInterface $file, ?bool $rename = false): string
    {
        //get path extension
        $filename = $file->getClientFilename();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        //check if extension is allowed
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        //check if file already exists
        if ($this->drive->getFilesystem()->fileExists($path . "/" . $filename)) {

            if ($rename) {
                $filename = $this->getNextFilename($path, $filename);
            } else {
                throw new Error("File already exists");
            }
        }

        //move file
        $this->drive->getFilesystem()->write($path . "/" . $filename, $file->getStream()->getContents());

        return $path . "/" . $filename;
    }

    #[Right('fs.file.move')]
    /**
     * @deprecated use lightDriveMoveFile
     */
    public function fsMoveFile(string $source, string $target): bool
    {
        $basename = basename($source);
        $this->drive->getFilesystem()->move($source, $target . "/" . $basename);
        return true;
    }
}
