<?php

namespace Light\Type;

use League\Flysystem\MountManager;
use Light\Filesystem\Node\File;
use Light\Filesystem\Node\Folder;
use Light\Filesystem\Node\Node;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type()]
class Filesystem
{

    #[Field]
    public function node(string $path, #[Autowire()] MountManager $mountManager): Node
    {
        $is_dir = $mountManager->directoryExists($path);
        if ($is_dir) {
            return new Folder(
                $path,
                $mountManager
            );
        } else {
            return new File(
                $path,
                $mountManager

            );
        }
    }
}
