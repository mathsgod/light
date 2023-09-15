<?php

namespace Light\Model;

use Light\App;
use Light\Input\User as InputUser;
use R\DB\Model;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\FailWith;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
#[MagicField(name: "user_id", outputType: "Int")]
#[MagicField(name: "username", outputType: "String")]
#[MagicField(name: "first_name", outputType: "String")]
#[MagicField(name: "last_name", outputType: "String")]
#[MagicField(name: "email", outputType: "String")]
#[MagicField(name: "phone", outputType: "String")]
#[MagicField(name: "addr1", outputType: "String")]
#[MagicField(name: "addr2", outputType: "String")]
#[MagicField(name: "addr3", outputType: "String")]
#[MagicField(name: "join_date", outputType: "String")]
#[MagicField(name: "expiry_date", outputType: "String")]
#[MagicField(name: "status", outputType: "Int")]
#[MagicField(name: "language", outputType: "String")]
#[MagicField(name: "default_page", outputType: "String")]

class User extends \Light\Model
{

    #[Field]
    public function isTwoFactorEnabled(): bool
    {
        if ($this->secret) {
            return true;
        }
        return false;
    }

    #[Field]
    public function isGranted(#[Autowire] App $app, string $right): bool
    {
        $rbac = $app->getRbac();

        foreach ($this->getRoles() as $role) {
            if ($rbac->isGranted($role, $right)) {
                return true;
            }
        }
        return false;
    }

    #[Field]
    /**
     * @return string[]
     */
    public function getRoles(): array
    {

        $q = UserRole::Query(["user_id" => $this->user_id]);
        $roles = [];
        foreach ($q as $r) {
            $roles[] = $r->role;
        }

        if (empty($roles)) {
            $roles[] = "Everyone";
        }

        return $roles;
    }


    #[Field]
    #[Right("user.delete")]
    #[FailWith(value: false)]
    public function canDelete(#[InjectUser] ?User $by): bool
    {
        //user cannot delete himself
        if ($by && $by->user_id == $this->user_id) {
            return false;
        }

        //only administrators can delete administrators
        if (in_array("Administrators", $this->getRoles()) && !in_array("Administrators", $by->getRoles())) {
            return false;
        }

        return parent::canDelete($by);
    }

    public function is(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }


    #[Field]
    public function getName(): string
    {
        return trim($this->first_name . " " . $this->last_name);
    }

    #[Field]
    public function canUpdate(#[InjectUser] ?User $by): bool
    {
        //only administrators can update administrators
        if ($this->is("Administrators") && !$by->is("Administrators")) {
            return false;
        }
        return true;
    }

    #[Field]
    /**
     * @return UserLog[]
     * @param ?mixed $filters
     */
    public function getUserLog($filters = [],  ?string $sort = ''): \R\DB\Query
    {
        return UserLog::Query(["user_id" => $this->user_id])->filters($filters)->sort($sort);
    }

    #[Field]
    /**
     * @return EventLog[]
     * @param ?mixed $filters
     */
    public function getEventLog($filters = [],  ?string $sort = ''): \R\DB\Query
    {
        return EventLog::Query(["user_id" => $this->user_id])->filters($filters)->sort($sort);
    }
}
