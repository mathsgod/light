<?php

namespace Light\Code;

use Laminas\Code\Generator\PropertyGenerator as GeneratorPropertyGenerator;
use Laminas\Code\Generator\TypeGenerator;

class PropertyGenerator extends GeneratorPropertyGenerator
{

    protected $attributes = [];
    protected ?string $rawType = null;

    public function addAttribute($attribute)
    {
        $this->attributes[] = $attribute;
    }

    /**
     * Set the property type as a raw string, bypassing TypeGenerator's
     * normalisation. TypeGenerator sorts/reorders union members and
     * prefixes the first class member with `\`, which produces a
     * `\Undefined|string|null` type for the Light partial-update
     * pattern. PHP 8.3.6 then refuses to assign `Undefined::VALUE`
     * (the default value) to that property because the leading `\` in
     * a union type triggers a strictness check during
     * ReflectionClass::getDefaultProperties(). Emitting the type as the
     * caller wrote it (`string|null|Undefined`) avoids the problem.
     */
    public function setRawType(string $type): void
    {
        $this->rawType = $type;
    }

    public function generate()
    {
        if ($this->rawType !== null) {
            $name         = $this->getName();
            $defaultValue = $this->getDefaultValue();
            $visibility   = $this->getVisibility();
            $readonly     = $this->isReadonly() ? ' readonly' : '';
            $static       = $this->isStatic() ? ' static' : '';

            $default = '';
            if ($defaultValue !== null) {
                // PropertyValueGenerator::generate() already appends ';'
                $default = ' = ' . $defaultValue->generate();
            } else {
                $default = ';';
            }

            $output = $this->indentation
                . $visibility
                . $readonly
                . $static
                . ' ' . $this->rawType
                . ' $' . $name
                . $default;

            if (($docBlock = $this->getDocBlock()) !== null) {
                $docBlock->setIndentation('    ');
                $output = $docBlock->generate() . $output;
            }

            return $this->indentation
                . implode("\n" . $this->indentation, $this->attributes)
                . "\n"
                . $output;
        }

        $content = parent::generate();

        $indentation = $this->getIndentation();
        $content = $indentation . implode("\n" . $indentation, $this->attributes) . "\n" . $content;

        return $content;
    }
}
