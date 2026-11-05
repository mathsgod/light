<?php

namespace Light\Framework\Filesystem\Event;

class FolderCreating
{
    public string $location;

    public function __construct(string $location)
    {
        $this->location = $location;
    }
}
