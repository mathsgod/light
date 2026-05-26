<?php

namespace Light\Tests\Model;

use Light\Model\User;
use Light\Model\UserRole;
use Light\Tests\TestCase;

class UserRoleTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::Create([
            'username'     => 'roletest_' . uniqid(),
            'join_date'    => date('Y-m-d'),
            'created_time' => date('Y-m-d H:i:s'),
        ]);
        $this->user->save();
    }

    public function testAssignRole(): void
    {
        UserRole::Create([
            'user_id' => $this->user->user_id,
            'role'    => 'Users',
        ])->save();

        $roles = $this->user->getRoles();
        $this->assertContains('Users', $roles);
    }

    public function testUserWithNoRoleDefaultsToEveryone(): void
    {
        // A freshly created user has no UserRole rows
        $roles = $this->user->getRoles();
        $this->assertEquals(['Everyone'], $roles);
    }

    public function testAssignMultipleRoles(): void
    {
        foreach (['Users', 'Power Users'] as $role) {
            UserRole::Create([
                'user_id' => $this->user->user_id,
                'role'    => $role,
            ])->save();
        }

        $roles = $this->user->getRoles();
        $this->assertContains('Users', $roles);
        $this->assertContains('Power Users', $roles);
        $this->assertCount(2, $roles);
    }

    public function testRemoveRole(): void
    {
        $ur = UserRole::Create([
            'user_id' => $this->user->user_id,
            'role'    => 'Users',
        ]);
        $ur->save();

        // Remove by querying then deleting
        foreach (UserRole::Query(['user_id' => $this->user->user_id, 'role' => 'Users']) as $r) {
            $r->delete();
        }

        $this->assertEquals(['Everyone'], $this->user->getRoles());
    }

    public function testIsRole(): void
    {
        UserRole::Create([
            'user_id' => $this->user->user_id,
            'role'    => 'Administrators',
        ])->save();

        $this->assertTrue($this->user->is('Administrators'));
        $this->assertFalse($this->user->is('Users'));
    }
}
