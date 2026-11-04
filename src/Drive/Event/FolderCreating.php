<?php

namespace Light\Framework\Drive\Event;

use Light\Framework\Drive\Drive;

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
