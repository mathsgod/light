<?php

namespace Light\Framework;

use Light\Framework\Model\EventLog;
use Light\Framework\Model\Notification;
use Light\Framework\Model\User;
use Psr\Container\ContainerInterface;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;

abstract class Model extends \Light\Db\Model
{
    private ?array $pendingAudit = null;

    public static bool $_log_insert = true;
    public static bool $_log_delete = true;
    public static bool $_log_update = true;
    public static ?ContainerInterface $container = null;

    public static function Notify(int $user_id, string $type, string $title, string $message, ?string $link = null): Notification
    {
        $n = Notification::Create([
            'user_id' => $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'is_read' => 0,
            'created_time' => date('Y-m-d H:i:s'),
        ]);
        $n->save();
        return $n;
    }

    public function __fields(): array
    {
        return $this->database()->metadata(static::class)->properties;
    }

    public function bind($data): static
    {
        $fields = $this->__fields();
        if (is_array($data)) {
            return parent::bind(array_intersect_key($data, array_flip($fields)));
        }
        $values = [];
        $ref = new \ReflectionObject($data);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (!in_array($name, $fields, true) || !$prop->isInitialized($data)) continue;
            $value = $prop->getValue($data);
            if ($value === \TheCodingMachine\GraphQLite\Undefined::VALUE) continue;
            $values[$name] = $value;
        }
        return parent::bind($values);
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


    private function auditUserId(): ?int
    {
        return self::$container?->get(Auth\Service::class)->getUser()?->user_id;
    }

    private function auditData(): array
    {
        $data = $this->jsonSerialize();
        foreach ($this->database()->metadata(static::class)->columns as $column) {
            if (str_contains(strtolower($column->type), 'blob')) unset($data[$column->name]);
        }
        return Util::Sanitize($data);
    }

    private function writeAudit(string $action, ?array $source, array $target): void
    {
        if (!(match ($action) {
            'Insert' => static::$_log_insert,
            'Update' => static::$_log_update,
            'Delete' => static::$_log_delete,
        })) return;
        \Light\Framework\Database\Sql::insert('EventLog', [
            'class' => static::class,
            'id' => is_array($id = $this->_id()) ? json_encode($id, JSON_THROW_ON_ERROR) : $id,
            'action' => $action,
            'source' => $source === null ? null : json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'target' => json_encode($target, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'user_id' => $this->auditUserId(),
            'created_time' => date('Y-m-d H:i:s'),
        ], $this->database()->connection());
    }

    public function delete(): int
    {
        $this->pendingAudit = ['Delete', null, $this->auditData()];
        return parent::delete();
    }

    public function save(): int
    {
        $action = $this->exists() ? 'Update' : 'Insert';
        $keys = $this->database()->metadata(static::class)->keys;
        $id = $this->_id();
        $condition = is_array($id) ? $id : [$keys[0] => $id];
        $source = $this->exists() ? (new \Light\Db\Query($this->database(), static::class))->where($condition)->first()?->auditData() : null;
        $userId = $this->auditUserId();
        $fields = $this->__fields();
        $prefix = $action === 'Insert' ? 'created' : 'updated';
        if (in_array($prefix . '_time', $fields, true)) $this->{$prefix . '_time'} = date('Y-m-d H:i:s');
        if ($userId !== null && in_array($prefix . '_by', $fields, true)) $this->{$prefix . '_by'} = $userId;
        $this->pendingAudit = [$action, $source, null];
        return parent::save();
    }
    protected function afterPersistence(bool $deleted): void
    {
        if ($this->pendingAudit === null) return;
        [$action, $source, $target] = $this->pendingAudit;
        $this->pendingAudit = null;
        $this->writeAudit($action, $source, $target ?? $this->auditData());
    }

}
