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
    public function node(string $location, #[Autowire()] MountManager $mountManager): ?Node
    {
        if ($mountManager->directoryExists($location)) {
            return new Folder(
                $location,
                $mountManager
            );
        } elseif ($mountManager->fileExists($location)) {
            return new File(
                $location,
                $mountManager

            );
        }
        return null;
    }
}
