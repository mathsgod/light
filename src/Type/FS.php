<?php


namespace Light\Type;

use League\Flysystem\Filesystem;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class FS
{
    private Filesystem $filesystem;

    #[Field]
    public string $name;

    public function __construct(string $name, Filesystem $filesystem)
    {
        $this->name = $name;
        $this->filesystem = $filesystem;
    }
}
