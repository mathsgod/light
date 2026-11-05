<?php

namespace Light\Framework\Filesystem\Event;

class FolderDeleting
{
    public string $location;

    public function __construct(string $location)
    {
        $this->location = $location;
    }
}
