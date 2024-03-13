<?php

namespace Light\Model;

use Error;
use Laminas\Db\Sql\Insert;
use Laminas\Permissions\Rbac\Role as RbacRole;
use Light\Model;
use Light\Util;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
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

    #[Field]
    public function getRevisionBy(): ?string
    {
        if ($this->user_id) {
            $user = User::Get($this->user_id);
            if ($user) {
                return $user->getName();
            }
        }
        return null;
    }

    public static function Remove(string $model_class, int $model_id)
    {
        return self::_table()->delete([
            "model_class" => $model_class,
            "model_id" => $model_id
        ]);
    }


    public static function Insert(int $user_id, string $model_class, int $model_id, Model $model, Model $target)
    {

        $source_array = Util::Sanitize($model->jsonSerialize());
        $target_array = Util::Sanitize($target->jsonSerialize());
        //find delta
        $delta = [];
        foreach ($source_array as $k => $v) {
            if ($source_array[$k] != $target_array[$k]) {
                $delta[$k] = $target_array[$k];
            }
        }

        return self::_table()->insert([
            "user_id" => $user_id,
            "model_id" => $model_id,
            "model_class" => $model_class,
            "model_content" =>  json_encode(Util::Sanitize($model->jsonSerialize()), JSON_UNESCAPED_UNICODE),
            "created_time" => date("Y-m-d H:i:s"),
            "delta" => json_encode($delta, JSON_UNESCAPED_UNICODE),
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

    #[Field(outputType: "mixed")]
    public function getDelta()
    {
        $delta = $this->delta;
        //sort by key
        ksort($delta);
        return $delta;
    }

    #[Field(outputType: "mixed")]
    public function getDiff()
    {
        $source = $this->getContent();
        $delta = $this->getDelta();
        $diff = [];


        $builder = new StrictUnifiedDiffOutputBuilder([
            'collapseRanges'      => true, // ranges of length one are rendered with the trailing `,1`
            'commonLineThreshold' => 6,    // number of same lines before ending a new hunk and creating a new one (if needed)
            'contextLines'        => 3,    // like `diff:  -u, -U NUM, --unified[=NUM]`, for patch/git apply compatibility best to keep at least @ 3
            'fromFile'            => 'Original',
            'fromFileDate'        => null,
            'toFile'              => 'New',
            'toFileDate'          => null,
        ]);
        $differ = new Differ($builder);
        foreach ($delta as $k => $v) {


            if (is_array($source[$k])) {
                $d = $differ->diff(json_encode($source[$k], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            } else {
                $d = $differ->diff($source[$k], $v);
            }
            $diff[$k] = $d;
        }
        return $diff;
    }
}
