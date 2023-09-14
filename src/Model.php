<?php

namespace Light;

use Exception;
use Light\Model\Config;
use Light\Model\EventLog;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

abstract class Model extends \R\DB\Model
{

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
    public function canRead(#[InjectUser] ?User $by): bool
    {
        return true;
    }

    public function save()
    {
        $service = self::GetSchema()->getContainer()->get(Auth\Service::class);

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

        EventLog::_table()->insert([
            "class" => static::class,
            "id" => $this->$key,
            "action" => $this->$key ? "Update" : "Insert",
            "source" => ["a"],
            "user_id" => $service->getUser()?->user_id,
            "created_time" => date("Y-m-d H:i:s"),
        ]);

        return parent::save();
    }
}
