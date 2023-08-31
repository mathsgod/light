<?php

namespace Light\Code;

use Laminas\Code\Generator\MethodGenerator as GeneratorMethodGenerator;

class MethodGenerator extends GeneratorMethodGenerator
{
    protected $attributes = [];

    public function addAttribute($attribute)
    {
        $this->attributes[] = $attribute;
    }

    public function generate()
    {
        $content = parent::generate();
        $indent = $this->getIndentation();

        $content =  $indent . implode("\n$indent", $this->attributes) . "\n" . $content;

        return $content;
    }
}
