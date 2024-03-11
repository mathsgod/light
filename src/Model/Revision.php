<?php

namespace Light\Model;

use Error;
use Laminas\Db\Sql\Insert;
use Laminas\Permissions\Rbac\Role as RbacRole;
use Light\Model;
use Light\Util;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "revision_id", outputType: "Int")]
#[MagicField(name: "user_id", outputType: "Int")]
class Revision extends \Light\Model
{
    public function retoreFields(array $fields)
    {
        $class = $this->model_class;
        $model_object = $class::Get($this->model_id);
        if ($model_object) {
            foreach ($fields as $field) {
                if (property_exists($model_object, $field)) {
                    $model_object->$field = $this->getContent()[$field];
                }
            }
            $model_object->save();
            return true;
        }
        return false;
    }

    public static function Insert(int $user_id, string $model_class, int $model_id, Model $model)
    {
        return self::_table()->insert([
            "user_id" => $user_id,
            "model_id" => $model_id,
            "model_class" => $model_class,
            "model_content" =>  json_encode(Util::Sanitize($model->jsonSerialize()), JSON_UNESCAPED_UNICODE),
            "created_time" => date("Y-m-d H:i:s"),
        ]);
    }

    #[Field(outputType: "mixed")]
    public function getContent()
    {
        $data = $this->model_content;
        //sort by key
        ksort($data);
        return $data;
    }
}
