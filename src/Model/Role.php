<?php

namespace Light\Model;

use Laminas\Permissions\Rbac\Role as RbacRole;
use R\DB\Model;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Role extends Model
{

    /**
     * @var RbacRole
     */
    protected $_role;

    #[Field]
    public function canDelete(#[InjectUser] ?User $by): bool
    {
        if ($this->getName() == "Administrators") return false;
        if ($this->getName() == "Users") return false;
        if ($this->getName() == "Power Users") return false;
        if ($this->getName() == "Everyone") return false;
        return true;
    }



    public function setRole(RbacRole $role)
    {
        $this->_role = $role;
    }

    public static function LoadByRole(RbacRole $role): ?Role
    {
        $r = new Role();
        $r->setRole($role);
        return $r;
    }

    #[Field]
    public function getName(): string
    {
        return $this->_role->getName();
    }

    #[Field]
    /**
     * @return Role[]
     */
    public function getParents(): array
    {
        $ps = [];
        foreach ($this->_role->getParents() as $p) {
            $ps[] = Role::LoadByRole($p);
        }
        return $ps;
    }

    #[Field]
    /**
     * @return string[]
     */
    public function getPermissions(bool $children = true): array
    {
        return $this->_role->getPermissions($children);
    }

    public function hasPermission(string $permission): bool
    {

        return $this->_role->hasPermission($permission);
    }
}
