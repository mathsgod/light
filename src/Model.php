<?php

namespace Light;

use Light\Model\EventLog;
use Light\Model\Revision;
use Light\Model\User;
use Light\Rbac\Rbac;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

abstract class Model extends \Light\Db\Model
{

    static $container;

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

    public static function SetContainer($container)
    {
        self::$container = $container;
    }

    #[Field]
    public function canUpdate(#[InjectUser] ?User $by): bool
    {
        /*        if ($container = self::GetSchema()->getContainer()) {
            $rbac = $container->get(App::class)->getRbac();
            assert($rbac instanceof Rbac);
            if ($user = $rbac->getUser($by->user_id)) {
                if ($user->can(static::class . "." . $this->_key() . ".write")) {
                    return true;
                }
            }
        } */
        return true;
    }

    #[Field]
    public function canView(#[InjectUser] ?User $by): bool
    {
        /*         if ($container = self::GetSchema()->getContainer()) {
            $rbac = $container->get(App::class)->getRbac();
            assert($rbac instanceof Rbac);
            if ($user = $rbac->getUser($by->user_id)) {
                if (
                    $user->can(static::class . "." . $this->_key() . ".read")
                    || $user->can(static::class . "." . $this->_key() . ".write")
                ) {
                    return true;
                }
            }
        } */


        return true;
    }


    public function delete()
    {
        $key = $this->_key();

        $user_id = null;
        if ($container = self::$container) {
            if ($service = $container->get(Auth\Service::class)) {
                $user_id = $service->getUser()?->user_id;
            }
        }

        EventLog::_table()->insert([
            "class" => static::class,
            "id" => $this->$key,
            "action" => "Delete",
            "source" => null,
            "target" => json_encode(Util::Sanitize($this->jsonSerialize()), JSON_UNESCAPED_UNICODE),
            "user_id" => $user_id,
            "created_time" => date("Y-m-d H:i:s"),
        ]);

        return parent::delete();
    }

    public function save()
    {
        $user_id = null;
        if ($container = self::$container) {
            if ($service = $container->get(Auth\Service::class)) {
                $user_id = $service->getUser()?->user_id;
            }
        }

        $key = $this->_key();
        if (!$this->$key) {
            $action = "Insert";
            if (in_array("created_time", $this->__fields())) {
                $this->created_time = date("Y-m-d H:i:s");
            }
            if (in_array("created_by", $this->__fields()) && $user_id) {
                $this->created_by = $user_id;
            }
        } else {
            $action = "Update";
            if (in_array("updated_time", $this->__fields())) {
                $this->updated_time = date("Y-m-d H:i:s");
            }
            if (in_array("updated_by", $this->__fields()) && $user_id) {
                $this->updated_by = $user_id;
            }
        }

        if ($action == "Update") {
            $source = static::get($this->$key);
            if ($source) {


                //filter out blob fields
                $attributes = $this->__attributes();

                foreach ($attributes as $attribute) {

                    if ($attribute["Type"] == "longblob" || $attribute["Type"] == "mediumblob" || $attribute["Type"] == "tinyblob") {
                        $field = $attribute["Field"];
                        $source->$field = null;
                    }
                }
                /* 
                $container = self::GetSchema()->getContainer();
               
                if ($container && $app = $container->get(App::class)) {
                    assert($app instanceof App);
                    if ($app->isRevisionEnabled(static::class)) {
                        Revision::Insert($user_id, static::class, $this->$key, $source, $this);
                    }
                }
 */
                $source = json_encode(Util::Sanitize($source->jsonSerialize()), JSON_UNESCAPED_UNICODE);
            }


            //filter out blob fields for target
            $attributes = $this->__attributes();
            $target = $this->jsonSerialize();

            foreach ($attributes as $attribute) {
                if ($attribute["Type"] == "longblob" || $attribute["Type"] == "mediumblob" || $attribute["Type"] == "tinyblob") {
                    $field = $attribute["Field"];
                    unset($target[$field]);
                }
            }

            $target = json_encode(Util::Sanitize($target), JSON_UNESCAPED_UNICODE);
        }

        if ($action == "Insert") {
            $source = null;
            $target = json_encode(Util::Sanitize($this->jsonSerialize()), JSON_UNESCAPED_UNICODE);
        }

        $result = parent::save();

        EventLog::_table()->insert([
            "class" => static::class,
            "id" => $this->$key,
            "action" => $action,
            "source" => $source,
            "target" => $target,
            "user_id" => $user_id,
            "created_time" => date("Y-m-d H:i:s"),
        ]);

        return $result;
    }
}
