<?php

namespace Light\Drive\Event;

use Light\Drive\Drive;
use Light\Drive\File;

class FileUploading
{
    public Drive $drive;
    public File $file;

    public function __construct(Drive $drive, File $file)
    {
        $this->drive = $drive;
        $this->file = $file;
    }
}
