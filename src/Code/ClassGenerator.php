<?php

namespace Light\Code;

use Laminas\Code\Generator\ClassGenerator as GeneratorClassGenerator;

class ClassGenerator extends GeneratorClassGenerator
{
    protected $attributes = [];

    public function addAttribute($attribute)
    {
        $this->attributes[] = $attribute;
    }

    /**
     * @inheritDoc
     */
    public function generate()
    {
        $content = parent::generate();

        $parts = explode("class {$this->getName()}", $content, 2);

        $parts[0] .= implode("\n", $this->attributes) . "\n";

        $content = implode("class {$this->getName()}", $parts);

        return $content;
    }
}
