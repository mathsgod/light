<?php


namespace Light\Type;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Storage
{
    #[Field]
    public function getFreeSpace(): float
    {
        return  disk_free_space(getcwd()) ?? 0;
    }

    #[Field]
    public function getTotalSpace(): float
    {
        return disk_total_space(getcwd()) ?? 0;
    }

    #[Field]
    public function getUsageSpace(): float
    {
        return $this->getTotalSpace() - $this->getFreeSpace();
    }
}
