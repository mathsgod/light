<?php

namespace Light\Controller;

use GraphQL\Error\Error;
use League\Flysystem\MountManager;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Light\App;
use Light\Model\EventLog;
use Light\Drive\File;
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

class FileSystemController
{

    const DISALLOW_EXT = ['zip', 'js', 'jsp', 'jsb', 'mhtml', 'mht', 'xhtml', 'xht', 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'shtml', 'jhtml', 'pl', 'sh', 'py', 'cgi', 'exe', 'application', 'gadget', 'hta', 'cpl', 'msc', 'jar', 'vb', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps2', 'psc1', 'psc2', 'msh', 'msh1', 'msh2', 'inf', 'reg', 'scf', 'msp', 'scr', 'dll', 'msi', 'vbs', 'bat', 'com', 'pif', 'cmd', 'vxd', 'cpl', 'htpasswd', 'htaccess'];

    #[Mutation(name: "lightFSCreateFolder")]
    #[Right("fs.folder:create")]
    public function createFolder(#[Autowire] MountManager $mountManager, string $location): bool
    {
        $mountManager->createDirectory($location);
        return true;
    }

    #[Mutation(name: "lightFSDeleteFolder")]
    #[Right("fs.folder:delete")]
    public function deleteFolder(#[Autowire] MountManager $mountManager, string $location): bool
    {
        $mountManager->deleteDirectory($location);
        return true;
    }

    #[Mutation(name: "lightFSRenameFolder")]
    #[Right("fs.folder:rename")]
    public function renameFolder(#[Autowire] MountManager $mountManager, string $location, string $newName): bool
    {
        $pathParts = pathinfo($location);
        $dirname = $pathParts['dirname'];
        if (str_ends_with($dirname, ':')) {
            $dirname .= '/';
        }

        $newLocation = $dirname . '/' . $newName;
        $mountManager->move($location, $newLocation);
        return true;
    }

    #[Mutation(name: "lightFSWriteFile")]
    #[Right("fs.file:write")]
    public function writeFile(#[Autowire] MountManager $mountManager, string $location, string $content): bool
    {
        //check filename starts with dot
        $pathParts = pathinfo($location);
        $basename = $pathParts['basename'];
        if (str_starts_with($basename, '.')) {
            throw new Error("File name cannot start with a dot");
        }

        $mountManager->write($location, $content);
        return true;
    }

    #[Mutation(name: "lightFSDeleteFile")]
    #[Right("fs.file:delete")]
    public function deleteFile(#[Autowire] MountManager $mountManager, string $location): bool
    {
        //check filename starts with dot
        $pathParts = pathinfo($location);
        $basename = $pathParts['basename'];
        if (str_starts_with($basename, '.')) {
            throw new Error("File name cannot start with a dot");
        }

        $mountManager->delete($location);
        return true;
    }

    #[Mutation(name: "lightFSRenameFile")]
    #[Right("fs.file:rename")]
    public function renameFile(#[Autowire] MountManager $mountManager, string $location, string $newName): bool
    {

        //check newName validity
        $extension = pathinfo($newName, PATHINFO_EXTENSION);
        if (in_array(strtolower($extension), self::DISALLOW_EXT)) {
            throw new Error("File extension not allowed");
        }

        //check newName starts with dot
        if (str_starts_with($newName, '.')) {
            throw new Error("File name cannot start with a dot");
        }

        $pathParts = pathinfo($location);
        $dirname = $pathParts['dirname'];
        if (str_ends_with($dirname, ':')) {
            $dirname .= '/';
        }

        $newLocation = $dirname . '/' . $newName;
        $mountManager->move($location, $newLocation);
        return true;
    }

    #[Mutation(name: "lightFSMove")]
    #[Right("fs.node:move")]
    public function moveNode(#[Autowire] MountManager $mountManager, string $from, string $to): bool
    {
        $mountManager->move($from, $to);
        return true;
    }


    #[Mutation(name: "lightFSUploadFile")]
    #[Right("fs.file:write")]
    public function uploadFile(#[Autowire] MountManager $mountManager, string $location, UploadedFileInterface $file, bool $rename = false): string
    {
        $pathParts = pathinfo($location);
        $dirname = $pathParts['dirname'];
        if (str_ends_with($dirname, ':')) {
            $dirname .= '/';
        }
        $filename = $pathParts['basename'];

        //check if file already exists
        if ($mountManager->fileExists($location)) {

            if ($rename) {
                $filename = $this->getNextFilename($mountManager, $dirname, $filename);
                $location = $dirname . '/' . $filename;
            } else {
                throw new Error("File already exists");
            }
        }

        //move file
        $mountManager->write($location, $file->getStream()->getContents());

        return $location;
    }

    private function getNextFilename(MountManager $mountManager, string $path, string $filename): string
    {
        $pathParts = pathinfo($filename);
        $name = $pathParts['filename'];
        $extension = isset($pathParts['extension']) ? '.' . $pathParts['extension'] : '';
        $i = 1;
        do {
            $newFilename = $name . ' (' . $i . ')' . $extension;
            $i++;
        } while ($mountManager->fileExists($path . '/' . $newFilename));
        return $newFilename;
    }


    #[Mutation(name: "lightFSupdate")]
    #[Right("filesystem.update")]
    /**
     * @param mixed $data
     */
    public function updateFileSystem($data, #[Autowire] App $app): bool
    {
        if (!$config = Config::Get(["name" => "fs"])) {
            return false;
        }
        $fs = json_decode($config->value);

        // find the file system by uuid
        $found = false;
        foreach ($fs as $k => $f) {
            if ($f->uuid == $data["uuid"]) {
                $fs[$k] = (array)$data;
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        // save the file system
        $config->value = json_encode($fs, JSON_UNESCAPED_UNICODE);
        $config->save();
        return true;
    }




    #[Mutation(name: "lightFSadd")]
    #[Right("filesystem.add")]
    /**
     * @param mixed $data
     */
    public function addFileSystem($data, #[Autowire] App $app): bool
    {
        if (!$config = Config::Get(["name" => "fs"])) {
            $config = Config::Create(["name" => "fs", "value" => "[]"]);
        }
        $fs = json_decode($config->value);

        $data["uuid"] = Uuid::uuid4()->toString();

        $fs[] = (array)$data;
        $config->value = json_encode($fs, JSON_UNESCAPED_UNICODE);
        $config->save();
        return true;
    }

    #[Query(outputType: "mixed")]
    #[Right("filesystem.list")]
    /**
     * @deprecated use app { listFileSystem } instead
     */
    public function listFileSystem(): array
    {
        if (!$config = Config::Get(["name" => "fs"])) {
            return [];
        }
        return json_decode($config->value);
    }

    #[Mutation(name: "lightFSdelete")]
    #[Right("filesystem.delete")]
    public function deleteFileSystem(string $uuid): bool
    {
        if (!$config = Config::Get(["name" => "fs"])) {
            return false;
        }
        $fs = json_decode($config->value);
        $newFs = [];
        foreach ($fs as $f) {
            if ($f->uuid != $uuid) {
                $newFs[] = $f;
            }
        }
        $config->value = json_encode($newFs, JSON_UNESCAPED_UNICODE);
        $config->save();
        return true;
    }
}
