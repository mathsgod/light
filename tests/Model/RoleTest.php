<?php

namespace Light\Tests\Model;

use Light\Model\Role;
use Light\Tests\TestCase;

class RoleTest extends TestCase
{
    private string $roleName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleName = 'TestRole_' . uniqid();
    }

    public function testCreateRole(): void
    {
        $role = Role::Create([
            'name'  => $this->roleName,
            'child' => 'Everyone',
        ]);
        $role->save();

        $this->assertNotNull($role->role_id);
        $this->assertGreaterThan(0, (int) $role->role_id);
    }

    public function testReadRole(): void
    {
        $role = Role::Create(['name' => $this->roleName, 'child' => 'Everyone']);
        $role->save();

        $found = Role::Get($role->role_id);

        $this->assertNotNull($found);
        $this->assertEquals($this->roleName, $found->name);
        $this->assertEquals('Everyone', $found->child);
    }

    public function testUpdateRole(): void
    {
        $role = Role::Create(['name' => $this->roleName, 'child' => 'Everyone']);
        $role->save();

        $role->child = 'Users';
        $role->save();

        $found = Role::Get($role->role_id);
        $this->assertEquals('Users', $found->child);
    }

    public function testDeleteRole(): void
    {
        $role = Role::Create(['name' => $this->roleName, 'child' => 'Everyone']);
        $role->save();
        $id = $role->role_id;

        $role->delete();

        $this->assertNull(Role::Get($id));
    }

    public function testMultipleChildRoles(): void
    {
        $children = ['Everyone', 'Users', 'Power Users'];

        foreach ($children as $child) {
            $r = Role::Create(['name' => $this->roleName, 'child' => $child]);
            $r->save();
        }

        $rows = Role::Query(['name' => $this->roleName])->toArray();
        $this->assertCount(3, $rows);

        $actualChildren = array_map(fn($r) => $r->child, $rows);
        sort($actualChildren);
        sort($children);
        $this->assertEquals($children, $actualChildren);
    }

    public function testDeleteAllChildRoles(): void
    {
        foreach (['Everyone', 'Users'] as $child) {
            Role::Create(['name' => $this->roleName, 'child' => $child])->save();
        }

        foreach (Role::Query(['name' => $this->roleName]) as $r) {
            $r->delete();
        }

        $this->assertCount(0, Role::Query(['name' => $this->roleName])->toArray());
    }
}
