<?php

namespace Light\Type;

use Light\Model\User;
use Light\Util;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Revision
{
    public $eventlog;
    public function __construct(\Light\Model\EventLog $eventlog)
    {
        $this->eventlog = $eventlog;
    }

    #[Field]
    public function getCreatedTime(): string
    {
        return $this->eventlog->created_time;
    }

    public function retoreFields(array $fields)
    {
        $class = $this->eventlog->class;
        $model_object = $class::Get($this->eventlog->id);
        if ($model_object) {
            foreach ($fields as $field) {
                assert($model_object instanceof \Light\Model);

                if (in_array($field, $model_object->__fields())) {
                    $model_object->$field = $this->getContent()[$field];
                }
            }
            $model_object->save();
            return true;
        }
        return false;
    }

    #[Field(name: "revision_id")]
    public function getRevisionId(): int
    {
        return $this->eventlog->eventlog_id;
    }

    #[Field]
    public function getRevisionBy(): ?string
    {
        if ($this->eventlog->user_id) {
            $user = User::Get($this->eventlog->user_id);
            if ($user) {
                return $user->getName();
            }
        }
        return null;
    }

    #[Field(outputType: "mixed")]
    public function getContent()
    {
        $data = $this->eventlog->source;
        //sort by key
        ksort($data);
        return $data;
    }

    #[Field(outputType: "mixed")]
    public function getDelta()
    {
        $source_array = Util::Sanitize($this->eventlog->source);
        $target_array = Util::Sanitize($this->eventlog->target);
        //find delta
        $delta = [];
        foreach ($source_array as $k => $v) {
            if ($source_array[$k] != $target_array[$k]) {
                $delta[$k] = $target_array[$k];
            }
        }
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
