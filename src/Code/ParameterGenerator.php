<?php

namespace Light\Code;

use Laminas\Code\Generator\ParameterGenerator as GeneratorParameterGenerator;

class ParameterGenerator extends GeneratorParameterGenerator
{

    protected $attributes = [];
    public function addAttribute($attribute)
    {
        $this->attributes[] = $attribute;
    }
    public function generate()
    {
        $content = parent::generate();

        $content = implode(" ", $this->attributes) . " " . $content;

        return $content;
    }
}
