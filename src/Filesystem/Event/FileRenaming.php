<?php

namespace Light\Framework\Filesystem\Event;

class FileRenaming
{
    public string $location;
    public string $newName;

    public function __construct(string $location, string $newName)
    {
        $this->location = $location;
        $this->newName = $newName;
    }
}
