<?php

namespace Light\Filesystem\Event;

class FileWriting
{
    public string $location;
    public string $content;

    public function __construct(string $location, string $content)
    {
        $this->location = $location;
        $this->content = $content;
    }
}
