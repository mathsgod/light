<?php

namespace Light\Drive\Event;

use Light\Drive\Drive;

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
