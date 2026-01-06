<?php

namespace Light\Filesystem\Event;

class FileUploading
{
    public string $location;
    public string $filename;

    public function __construct(string $location, string $filename)
    {
        $this->location = $location;
        $this->filename = $filename;
    }
}
