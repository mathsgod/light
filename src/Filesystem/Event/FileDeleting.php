<?php

namespace Light\Framework\Filesystem\Event;

class FileDeleting
{
    public string $location;

    public function __construct(string $location)
    {
        $this->location = $location;
    }
}
