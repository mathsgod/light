<?php

namespace Light\Controller;

use GraphQL\Error\Error;
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
    #[Mutation]
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

    #[Mutation]
    #[Right("filesystem.delete")]
    public function deleteFileSystem(string $name): bool
    {
        if (!$config = Config::Get(["name" => "fs"])) {
            return false;
        }
        $fs = json_decode($config->value);
        $newFs = [];
        foreach ($fs as $f) {
            if ($f->name != $name) {
                $newFs[] = $f;
            }
        }
        $config->value = json_encode($newFs, JSON_UNESCAPED_UNICODE);
        $config->save();
        return true;
    }
}
