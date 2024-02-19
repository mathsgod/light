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
use Light\Input\FileSystem as InputFileSystem;
use Light\Model\Config;
use TheCodingMachine\GraphQLite\Annotations\Autowire;

class FileSystemController
{
    #[Mutation]
    #[Right("fs.add")]
    public function addFileSystem(InputFileSystem $data, #[Autowire] App $app): bool
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
}
