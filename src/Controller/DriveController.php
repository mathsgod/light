<?php

namespace Light\Controller;

use GraphQL\Error\Error;
use League\Flysystem\Filesystem;
use Light\App;
use Psr\Http\Message\UploadedFileInterface;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Autowire;

class DriveController
{

    const DISALLOW_EXT = ['zip', 'js', 'jsp', 'jsb', 'mhtml', 'mht', 'xhtml', 'xht', 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'shtml', 'jhtml', 'pl', 'sh', 'py', 'cgi', 'exe', 'application', 'gadget', 'hta', 'cpl', 'msc', 'jar', 'vb', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps2', 'psc1', 'psc2', 'msh', 'msh1', 'msh2', 'inf', 'reg', 'scf', 'msp', 'scr', 'dll', 'msi', 'vbs', 'bat', 'com', 'pif', 'cmd', 'vxd', 'cpl', 'htpasswd', 'htaccess'];

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

    #[Mutation]
    #[Right("fs.file.upload")]
    public function lightDriveUploadFile(#[Autowire] App $app, int $index, string $path, UploadedFileInterface $file, ?bool $rename = false): string
    {
        //get path extension
        $filename = $file->getClientFilename();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        //check if extension is allowed
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        $drive = $app->getDrive($index);
        $fs = $drive->getFilesystem();

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


    #[Mutation]
    #[Right("fs.folder.create")]
    public function lightDriveCreateFolder(#[Autowire] App $app, int $index, string $path): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->createDirectory($path);
        return true;
    }

    #[Mutation]
    #[Right("fs.folder.delete")]
    public function lightDriveDeleteFolder(#[Autowire] App $app, int $index, string $path): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->deleteDirectory($path);
        return true;
    }

    #[Mutation]
    #[Right("fs.folder.rename")]
    public function lightDriveRenameFolder(#[Autowire] App $app, int $index, string $path, string $name): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->move($path, dirname($path) . "/" . $name);
        return true;
    }

    //lightDriveWriteFile
    #[Mutation]
    #[Right("fs.file.write")]
    public function lightDriveWriteFile(#[Autowire] App $app, int $index, string $path, string $content): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->write($path, $content);
        return true;
    }

    //lightDriveDeleteFile
    #[Mutation]
    #[Right("fs.file.delete")]
    public function lightDriveDeleteFile(#[Autowire] App $app, int $index, string $path): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->delete($path);
        return true;
    }

    //lightDriveRenameFile
    #[Mutation]
    #[Right("fs.file.rename")]
    public function lightDriveRenameFile(#[Autowire] App $app, int $index, string $path, string $name): bool
    {
        //check extension
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        $drive = $app->getDrive($index);
        $drive->getFilesystem()->move($path, dirname($path) . "/" . $name);
        return true;
    }

    //lightDriveMoveFile
    #[Mutation]
    #[Right("fs.file.move")]
    public function lightDriveMoveFile(#[Autowire] App $app, int $index, string $source, string $destination): bool
    {
        $drive = $app->getDrive($index);
        $basename = basename($source);
        $drive->getFilesystem()->move($source, $destination . "/" . $basename);
        return true;
    }
}
