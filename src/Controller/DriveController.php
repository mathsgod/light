<?php

namespace Light\Controller;

use FolderCreating;
use GraphQL\Error\Error;
use League\Flysystem\Filesystem;
use Light\App;
use Light\Drive\Event\FileUploading;
use Light\Drive\File;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Autowire;

class DriveController
{

    const DISALLOW_EXT = ['zip', 'js', 'jsp', 'jsb', 'mhtml', 'mht', 'xhtml', 'xht', 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'shtml', 'jhtml', 'pl', 'sh', 'py', 'cgi', 'exe', 'application', 'gadget', 'hta', 'cpl', 'msc', 'jar', 'vb', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps2', 'psc1', 'psc2', 'msh', 'msh1', 'msh2', 'inf', 'reg', 'scf', 'msp', 'scr', 'dll', 'msi', 'vbs', 'bat', 'com', 'pif', 'cmd', 'vxd', 'cpl', 'htpasswd', 'htaccess'];


    #[Mutation(name: "lightDriveUploadTempFile")]
    #[Right("fs.file.upload")]
    public function uploadTempFile(#[Autowire] App $app, int $index, UploadedFileInterface $file): File
    {
        //get path extension
        $filename = $file->getClientFilename();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        //check if extension is allowed
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        //random name
        $filename = Uuid::uuid4()->toString() . "." . $ext;

        //move file

        $drive = $app->getDrive($index);
        $drive->getFilesystem()->write("temp/" . $filename, $file->getStream()->getContents());

        $list = $drive->getFilesystem()->listContents("temp", false);
        foreach ($list as $file) {
            if ($file->path() === "temp/" . $filename) {
                return new File($drive, $file);
            }
        }

        throw new Error("File not found");
    }

    private function getNextFilename(Filesystem $fs, string $path, $filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = pathinfo($filename, PATHINFO_FILENAME);

        $i = 1;
        while ($fs->fileExists($path . "/" . $filename . "($i)." . $ext)) {
            $i++;
        }
        return $filename . "($i)." . $ext;
    }

    #[Mutation(name: "lightDriveUploadFile")]
    #[Right("fs.file.upload")]
    public function uploadFile(#[Autowire] App $app, int $index, string $path, UploadedFileInterface $file, ?bool $rename = false): string
    {
        $filename = $file->getClientFilename();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        //check if extension is allowed
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        $drive = $app->getDrive($index);

        /** @var FileUploading $event **/
        $event = $app->eventDispatcher()->dispatch(new FileUploading($drive, $path, $file));

        $filename = $event->file->getClientFilename();
        $path = $event->path;
        $file = $event->file;

        $fs = $event->drive->getFilesystem();


        //check if file already exists
        if ($fs->fileExists($path . "/" . $filename)) {

            if ($rename) {
                $filename = $this->getNextFilename($fs, $path, $filename);
            } else {
                throw new Error("File already exists");
            }
        }

        //move file
        $fs->write($path . "/" . $filename, $file->getStream()->getContents());

        return $path . "/" . $filename;
    }


    #[Mutation(name: "lightDriveCreateFolder")]
    #[Right("fs.folder.create")]
    public function createFolder(#[Autowire] App $app, int $index, string $path): bool
    {
        $drive = $app->getDrive($index);

        /** @var FolderCreating $event **/
        $event = $app->eventDispatcher()->dispatch(new FolderCreating($drive, $path));

        $drive = $event->drive;
        $path = $event->path;

        $drive->getFilesystem()->createDirectory($path);
        return true;
    }

    #[Mutation(name: "lightDriveDeleteFolder")]
    #[Right("fs.folder.delete")]
    public function deleteFolder(#[Autowire] App $app, int $index, string $path): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->deleteDirectory($path);
        return true;
    }

    #[Mutation(name: "lightDriveRenameFolder")]
    #[Right("fs.folder.rename")]
    public function renameFolder(#[Autowire] App $app, int $index, string $path, string $name): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->move($path, dirname($path) . "/" . $name);
        return true;
    }

    //lightDriveWriteFile
    #[Mutation(name: "lightDriveWriteFile")]
    #[Right("fs.file.write")]
    public function writeFile(#[Autowire] App $app, int $index, string $path, string $content): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->write($path, $content);
        return true;
    }

    //lightDriveDeleteFile
    #[Mutation(name: "lightDriveDeleteFile")]
    #[Right("fs.file.delete")]
    public function deleteFile(#[Autowire] App $app, int $index, string $path): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->delete($path);
        return true;
    }

    //lightDriveRenameFile
    #[Mutation(name: "lightDriveRenameFile")]
    #[Right("fs.file.rename")]
    public function renameFile(#[Autowire] App $app, int $index, string $path, string $name): bool
    {
        //check extension
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        $drive = $app->getDrive($index);
        $drive->getFilesystem()->move($path, dirname($path) . "/" . $name);
        return true;
    }

    //lightDriveMoveFile
    #[Mutation(name: "lightDriveMoveFile")]
    #[Right("fs.file.move")]
    public function moveFile(#[Autowire] App $app, int $index, string $source, string $destination): bool
    {
        $drive = $app->getDrive($index);
        $basename = basename($source);
        $drive->getFilesystem()->move($source, $destination . "/" . $basename);
        return true;
    }
}
