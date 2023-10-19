<?php

namespace Light;

use Light\Model\EventLog;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

abstract class Model extends \R\DB\Model
{

    #[Field] public function createdTime(): string
    {
        return $this->created_time;
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
        return $this->updated_time;
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
            "target" => json_encode($this->jsonSerialize()),
            "user_id" => $user_id,
            "created_time" => date("Y-m-d H:i:s"),
        ]);
        return parent::delete();
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
                $source = json_encode($source->jsonSerialize());
            }
            $target = json_encode($this->jsonSerialize());
        }

        if ($action == "Insert") {
            $source = null;
            $target = json_encode($this->jsonSerialize());
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
