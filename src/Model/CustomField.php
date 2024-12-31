<?php

namespace Light\Model;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "custom_field_id", outputType: "Int!")]
#[MagicField(name: "name", outputType: "String")]
#[MagicField(name: "label", outputType: "String")]
#[MagicField(name: "model", outputType: "String")]
#[MagicField(name: "type", outputType: "String")]
#[MagicField(name: "placeholder", outputType: "String")]
#[MagicField(name: "options", outputType: "[String]")]
#[MagicField(name: "validation", outputType: "String")]
#[MagicField(name: "default_value", outputType: "String")]
#[MagicField(name: "order", outputType: "Int")]
#[MagicField(name: "help", outputType: "String")]
class CustomField extends \Light\Model
{

    public function getFormKitSchema()
    {
        $schema = [];
        if ($this->type == "text") {
            $schema['$formkit'] = 'l-input';
        }

        if ($this->type == "textarea") {
            $schema['$formkit'] = 'l-input';
            $schema["inputType"] = "textarea";
        }

        if ($this->type == "select") {
            $schema['$formkit'] = 'l-select';
            $schema["options"] = $this->options;
        }

        if ($this->type == "date") {
            $schema['$formkit'] = 'l-date-picker';
        }

        if ($this->type == "time") {
            $schema['$formkit'] = 'l-time-picker';
        }

        if($this->default_value){
            $schema["value"] = $this->default_value;
        }


        $schema = [
            ...$schema,
            'name' => $this->name,
            'label' => $this->label,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'validation' => $this->validation,
        ];

        return $schema;
    }
}
