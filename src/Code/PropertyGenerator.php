<?php

namespace Light\Code;

use Laminas\Code\Generator\PropertyGenerator as GeneratorPropertyGenerator;

class PropertyGenerator extends GeneratorPropertyGenerator
{

    protected $attributes = [];
    public function addAttribute($attribute)
    {
        $this->attributes[] = $attribute;
    }
    public function generate()
    {
        $content = parent::generate();

        $indentation = $this->getIndentation();
        $content = $indentation . implode("\n" . $indentation, $this->attributes) . "\n" . $content;

        return $content;
    }
}
