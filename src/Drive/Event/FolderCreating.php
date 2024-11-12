<?php

use Light\Drive\Drive;
use Light\Drive\Event;
use Psr\EventDispatcher\StoppableEventInterface;

class FolderCreating
{
    public Drive $drive;
    public string $path;

    public function __construct(Drive $drive, string $path)
    {
        $this->drive = $drive;
        $this->path = $path;
    }
}
