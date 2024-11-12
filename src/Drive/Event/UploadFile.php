<?php

namespace Light\Drive\Event;

use Light\Drive;
use Light\Drive\File;

class FileUploaded
{
    public Drive $drive;
    public File $file;

    public function __construct(Drive $drive, File $file)
    {
        $this->drive = $drive;
        $this->file = $file;
    }
}
