<?php

namespace Light\Framework\Code;

use Laminas\Code\Generator\ClassGenerator as GeneratorClassGenerator;

class ClassGenerator extends GeneratorClassGenerator
{
    private array $lightAttributes = [];

    public function addAttribute($attribute)
    {
        if (!is_string($attribute) && method_exists(parent::class, 'addAttribute')) {
            return parent::addAttribute($attribute);
        }
        if (!is_string($attribute)) throw new \InvalidArgumentException('Expected an attribute string or a supported native attribute generator');
        $this->lightAttributes[] = $attribute;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function generate()
    {
        $content = parent::generate();

        $parts = explode("class {$this->getName()}", $content, 2);

        $parts[0] .= implode("\n", $this->lightAttributes) . "\n";

        $content = implode("class {$this->getName()}", $parts);

        return $content;
    }
}
