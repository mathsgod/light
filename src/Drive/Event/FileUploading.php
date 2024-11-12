<?php

namespace Light\Drive\Event;

use Light\Drive\Drive;
use Psr\Http\Message\UploadedFileInterface;

class FileUploading
{
    public Drive $drive;
    public string $path;
    public UploadedFileInterface $file;

    public function __construct(Drive $drive, string $path, UploadedFileInterface $file)
    {
        $this->drive = $drive;
        $this->path = $path;
        $this->file = $file;
    }
}
