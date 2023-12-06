<?php

namespace Light;

use Light\Model\EventLog;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

abstract class Model extends \R\DB\Model
{

    public function bind($data)
    {

        $fields = $this->__fields();
        foreach ($data as $k => $v) {
            if ($v === null) continue;

            if (!in_array($k, $fields)) continue;

            $this->$k = $v;
        }
    }


    #[Field] public function createdTime(): string
    {
        return $this->created_time ?? "";
    }

    #[Field] public function createdBy(): string
    {
        if ($this->created_by) {
            if ($user = User::Get($this->created_by)) {
                return $user->getName();
            }
        }
        return "";
    }

    #[Field] public function updatedTime(): string
    {
        return $this->updated_time ?? "";
    }

    #[Field] public function updatedBy(): string
    {
        if ($this->updated_by) {
            if ($user = User::Get($this->updated_by)) {
                return $user->getName();
            }
        }
        return "";
    }


    #[Field]
    public function canDelete(#[InjectUser] ?User $by): bool
    {
        return true;
    }

    #[Field]
    public function canUpdate(#[InjectUser] ?User $by): bool
    {
        return true;
    }

    #[Field]
    public function canView(#[InjectUser] ?User $by): bool
    {
        return true;
    }

    public function delete()
    {
        $key = $this->_key();

        $user_id = null;
        if ($container = self::GetSchema()->getContainer()) {
            if ($service = $container->get(Auth\Service::class)) {
                $user_id = $service->getUser()?->user_id;
            }
        }

        EventLog::_table()->insert([
            "class" => static::class,
            "id" => $this->$key,
            "action" => "Delete",
            "source" => null,
            "target" => json_encode($this->sanitize($this->jsonSerialize())),
            "user_id" => $user_id,
            "created_time" => date("Y-m-d H:i:s"),
        ]);
        return parent::delete();
    }

    private function sanitize(array $data)
    {

        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out[$k] = $this->sanitize($v);
            } else {
                $out[$k] = iconv('UTF-8', 'UTF-8//IGNORE', $v);
            }
        }
        return $out;
    }

    public function save()
    {

        $key = $this->_key();
        if (!$this->$key) {
            if (in_array("created_time", $this->__fields())) {
                $this->created_time = date("Y-m-d H:i:s");
            }
        } else {
            if (in_array("updated_time", $this->__fields())) {
                $this->updated_time = date("Y-m-d H:i:s");
            }
        }


        $user_id = null;
        if ($container = self::GetSchema()->getContainer()) {
            if ($service = $container->get(Auth\Service::class)) {
                $user_id = $service->getUser()?->user_id;
            }
        }

        $action = $this->$key ? "Update" : "Insert";

        if ($action == "Update") {
            $source = static::get($this->$key);
            if ($source) {
                $source = json_encode($this->sanitize($source->jsonSerialize()));
            }

            $target = json_encode($this->sanitize($this->jsonSerialize()));
        }

        if ($action == "Insert") {
            $source = null;
            $target = json_encode($this->sanitize($this->jsonSerialize()));
        }

        EventLog::_table()->insert([
            "class" => static::class,
            "id" => $this->$key,
            "action" => $this->$key ? "Update" : "Insert",
            "source" => $source,
            "target" => $target,
            "user_id" => $user_id,
            "created_time" => date("Y-m-d H:i:s"),
        ]);

        return parent::save();
    }
}
