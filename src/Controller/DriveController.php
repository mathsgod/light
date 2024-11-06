<?php

namespace Light\Controller;

use GraphQL\Error\Error;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Light\App;
use Light\Model\EventLog;
use Light\Type\FS\File;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\UseInputType;
use Light\Model\Config;
use TheCodingMachine\GraphQLite\Annotations\Autowire;

class DriveController
{

    const DISALLOW_EXT = ['zip', 'js', 'jsp', 'jsb', 'mhtml', 'mht', 'xhtml', 'xht', 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'shtml', 'jhtml', 'pl', 'sh', 'py', 'cgi', 'exe', 'application', 'gadget', 'hta', 'cpl', 'msc', 'jar', 'vb', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps2', 'psc1', 'psc2', 'msh', 'msh1', 'msh2', 'inf', 'reg', 'scf', 'msp', 'scr', 'dll', 'msi', 'vbs', 'bat', 'com', 'pif', 'cmd', 'vxd', 'cpl', 'htpasswd', 'htaccess'];

    #[Mutation]
    public function lightDriveCreateFolder(int $index = 0, string $path, #[Autowire] App $app): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->createDirectory($path);
        return true;
    }

    #[Mutation]
    public function lightDriveDeleteFolder(int $index = 0, string $path, #[Autowire] App $app): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->deleteDirectory($path);
        return true;
    }

    #[Mutation]
    public function lightDriveRenameFolder(int $index = 0, string $path, string $name, #[Autowire] App $app): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->move($path, dirname($path) . "/" . $name);
        return true;
    }

    //lightDriveWriteFile
    #[Mutation]
    public function lightDriveWriteFile(int $index = 0, string $path, string $content, #[Autowire] App $app): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->write($path, $content);
        return true;
    }

    //lightDriveDeleteFile
    #[Mutation]
    public function lightDriveDeleteFile(int $index = 0, string $path, #[Autowire] App $app): bool
    {
        $drive = $app->getDrive($index);
        $drive->getFilesystem()->delete($path);
        return true;
    }

    //lightDriveRenameFile
    #[Mutation]
    public function lightDriveRenameFile(int $index = 0, string $path, string $name, #[Autowire] App $app): bool
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
    public function lightDriveMoveFile(int $index = 0, string $source, string $destination, #[Autowire] App $app): bool
    {
        $drive = $app->getDrive($index);
        $basename = basename($source);
        $drive->getFilesystem()->move($source, $destination . "/" . $basename);
        return true;
    }
}
