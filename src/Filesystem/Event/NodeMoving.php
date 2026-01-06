<?php

namespace Light\Filesystem\Event;

class NodeMoving
{
    public string $from;
    public string $to;

    public function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to = $to;
    }
}
