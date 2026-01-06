<?php

namespace Light\Controller;

use GraphQL\Error\Error;
use League\Flysystem\MountManager;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Light\App;

use Light\Filesystem\Event\FolderCreating;
use Light\Filesystem\Event\FolderDeleting;
use Light\Filesystem\Event\FolderRenaming;
use Light\Filesystem\Event\FileWriting;
use Light\Filesystem\Event\FileDeleting;
use Light\Filesystem\Event\FileRenaming;
use Light\Filesystem\Event\NodeMoving;
use Light\Filesystem\Event\FileUploading;
use Light\Model\EventLog;
use Light\Filesystem\Node\File;
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
    public function createFolder(#[Autowire] App $app, string $location): bool
    {
        //check folder name starts with dot
        $pathParts = pathinfo($location);
        $basename = $pathParts['basename'];
        if (str_starts_with($basename, '.')) {
            throw new Error("Folder name cannot start with a dot");
        }

        /** @var FolderCreating $event **/
        $event = $app->eventDispatcher()->dispatch(new FolderCreating($location));
        $app->getMountManager()->createDirectory($event->location);
        return true;
    }

    #[Mutation(name: "lightFSDeleteFolder")]
    #[Right("fs.folder:delete")]
    public function deleteFolder(#[Autowire] App $app, string $location): bool
    {
        //check folder name starts with dot
        $pathParts = pathinfo($location);
        $basename = $pathParts['basename'];
        if (str_starts_with($basename, '.')) {
            throw new Error("Folder name cannot start with a dot");
        }

        /** @var FolderDeleting $event **/
        $event = $app->eventDispatcher()->dispatch(new FolderDeleting($location));
        $app->getMountManager()->deleteDirectory($event->location);
        return true;
    }

    #[Mutation(name: "lightFSRenameFolder")]
    #[Right("fs.folder:rename")]
    public function renameFolder(#[Autowire] App $app, #[Autowire] MountManager $mountManager, string $location, string $newName): bool
    {
        //check newName starts with dot
        if (str_starts_with($newName, '.')) {
            throw new Error("Folder name cannot start with a dot");
        }

        /** @var FolderRenaming $event **/
        $event = $app->eventDispatcher()->dispatch(new FolderRenaming($location, $newName));

        $pathParts = pathinfo($event->location);
        $dirname = $pathParts['dirname'];
        if (str_ends_with($dirname, ':')) {
            $dirname .= '/';
        }

        $newLocation = $dirname . '/' . $event->newName;
        $mountManager->move($event->location, $newLocation);
        return true;
    }

    #[Mutation(name: "lightFSWriteFile")]
    #[Right("fs.file:write")]
    public function writeFile(#[Autowire] App $app, #[Autowire] MountManager $mountManager, string $location, string $content): bool
    {
        //check filename starts with dot
        $pathParts = pathinfo($location);
        $basename = $pathParts['basename'];
        if (str_starts_with($basename, '.')) {
            throw new Error("File name cannot start with a dot");
        }

        /** @var FileWriting $event **/
        $event = $app->eventDispatcher()->dispatch(new FileWriting($location, $content));
        $mountManager->write($event->location, $event->content);
        return true;
    }

    #[Mutation(name: "lightFSDeleteFile")]
    #[Right("fs.file:delete")]
    public function deleteFile(#[Autowire] App $app, #[Autowire] MountManager $mountManager, string $location): bool
    {
        //check filename starts with dot
        $pathParts = pathinfo($location);
        $basename = $pathParts['basename'];
        if (str_starts_with($basename, '.')) {
            throw new Error("File name cannot start with a dot");
        }

        /** @var FileDeleting $event **/
        $event = $app->eventDispatcher()->dispatch(new FileDeleting($location));
        $mountManager->delete($event->location);
        return true;
    }

    #[Mutation(name: "lightFSRenameFile")]
    #[Right("fs.file:rename")]
    public function renameFile(#[Autowire] App $app, #[Autowire] MountManager $mountManager, string $location, string $newName): bool
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

        /** @var FileRenaming $event **/
        $event = $app->eventDispatcher()->dispatch(new FileRenaming($location, $newName));

        $pathParts = pathinfo($event->location);
        $dirname = $pathParts['dirname'];
        if (str_ends_with($dirname, ':')) {
            $dirname .= '/';
        }

        $newLocation = $dirname . '/' . $event->newName;
        $mountManager->move($event->location, $newLocation);
        return true;
    }

    #[Mutation(name: "lightFSMove")]
    #[Right("fs.node:move")]
    public function moveNode(#[Autowire] App $app, #[Autowire] MountManager $mountManager, string $from, string $to): bool
    {
        //check if from starts with dot
        $pathParts = pathinfo($from);
        $basename = $pathParts['basename'];
        if (str_starts_with($basename, '.')) {
            throw new Error("File/Folder name cannot start with a dot");
        }


        $basename = basename($from);
        if (str_ends_with($to, '/')) {
            $to = $to . $basename;
        } else {
            $to = $to . '/' . $basename;
        }

        /** @var NodeMoving $event **/
        $event = $app->eventDispatcher()->dispatch(new NodeMoving($from, $to));

        try {
            $mountManager->move($event->from, $event->to);
        } catch (\Exception $e) {
            throw new Error($e->getMessage());
        }

        return true;
    }


    #[Mutation(name: "lightFSUploadBase64")]
    #[Right("fs.file:write")]
    public function uploadBase64(#[Autowire] App $app, #[Autowire] MountManager $mountManager, string $location, string $base64): File
    {
        //check if extension is allowed
        $ext = pathinfo($location, PATHINFO_EXTENSION);
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        //check starting with dot
        $basename = pathinfo($location, PATHINFO_BASENAME);
        if (str_starts_with($basename, '.')) {
            throw new Error("File name cannot start with a dot");
        }


        // 1. 處理 Data URI Header (例如: data:image/png;base64,...)
        // 如果傳入的是完整的 Data URI，我們只需要後面的內容
        if (str_contains($base64, ',')) {
            $parts = explode(',', $base64);
            $base64Data = end($parts);
        } else {
            $base64Data = $base64;
        }

        // 2. 解碼 Base64 成為二進位字串
        $content = base64_decode($base64Data, true);

        if ($content === false) {
            throw new \Exception("Invalid base64 string provided.");
        }

        /** @var FileUploading $event **/
        $event = $app->eventDispatcher()->dispatch(new FileUploading($location, $basename));

        // 3. 寫入檔案系統 (Flysystem)
        // 使用 write 方法，如果檔案已存在會覆蓋
        $mountManager->write($event->location, $content);

        // 4. 回傳 File 物件供前端更新 UI
        return new File($event->location);
    }

    #[Mutation(name: "lightFSUploadFile")]
    #[Right("fs.file:write")]
    public function uploadFile(#[Autowire] App $app, #[Autowire] MountManager $mountManager, string $location, UploadedFileInterface $file, bool $rename = false): bool
    {

        $filename = $file->getClientFilename();
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        //check if extension is allowed
        if (in_array($ext, self::DISALLOW_EXT)) throw new Error("File type not allowed");

        $location = $location . '/' . $filename;

        //check if file already exists
        if ($mountManager->fileExists($location)) {

            if ($rename) {
                $filename = $this->getNextFilename($mountManager, $location, $filename);
                $location = $location . '/' . $filename;
            } else {
                throw new Error("File already exists");
            }
        }

        /** @var FileUploading $event **/
        $event = $app->eventDispatcher()->dispatch(new FileUploading($location, $filename));

        //move file
        $mountManager->write($event->location, $file->getStream()->getContents());

        return true;
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
