<?php

namespace Light\Filesystem\Node;

use League\Flysystem\MountManager;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type()]
interface Node
{
    #[Field()]
    public function getName(): string;

    #[Field]
    public function getPath(): string;

    #[Field]
    public function getLastModified(#[Autowire] MountManager $mountManager): int;

    #[Field]
    public function getLocation(): string;
}
