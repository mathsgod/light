<?php

namespace Light;

use Light\Db\Proxy;
use Light\Model\EventLog;
use Light\Model\Revision;
use Light\Model\User;
use Psr\Container\ContainerInterface;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

abstract class Model extends \Light\Db\Model
{
    public static bool $_log_insert = true;
    public static bool $_log_delete = true;
    public static bool $_log_update = true;
    public static ?ContainerInterface $container = null;

    public function __fields(): array
    {
        return self::_table()->columns()->map(function ($column) {
            return $column->getName();
        })->toArray();
    }

    public function bind($data)
    {
        $fields = $this->__fields();
        $items = is_object($data) ? get_object_vars($data) : $data;
        foreach ($items as $k => $v) {
            if (!in_array($k, $fields)) continue;
            $this->$k = $v;
        }
    }


    #[Field] public function createdTime(): ?string
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

    #[Field] public function updatedTime(): ?string
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
        return $by !== null;
    }

    public static function SetContainer(?ContainerInterface $container): void
    {
        self::$container = $container;
    }

    #[Field]
    public function canUpdate(#[InjectUser] ?User $by): bool
    {
        return $by !== null;
    }

    #[Field]
    public function canView(#[InjectUser] ?User $by): bool
    {
        return $by !== null;
    }


    public function delete(): mixed
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

    public function save(): mixed
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

                    if ($attribute->getDataType() == "longblob" || $attribute->getDataType() == "mediumblob" || $attribute->getDataType() == "tinyblob") {
                        $field = $attribute->getName();
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
                if ($attribute->getDataType() == "longblob" || $attribute->getDataType() == "mediumblob" || $attribute->getDataType() == "tinyblob") {
                    $field = $attribute->getName();
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

        if (
            static::$_log_insert && $action == "Insert"
            || static::$_log_update && $action == "Update"
            || static::$_log_delete && $action == "Delete"
        ) {

            EventLog::_table()->insert([
                "class" => static::class,
                "id" => $this->$key,
                "action" => $action,
                "source" => $source,
                "target" => $target,
                "user_id" => $user_id,
                "created_time" => date("Y-m-d H:i:s"),
            ]);
        }
        return $result;
    }
}
