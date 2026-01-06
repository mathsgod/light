<?php

namespace Light\Filesystem\Event;

class FolderDeleting
{
    public string $location;

    public function __construct(string $location)
    {
        $this->location = $location;
    }
}
