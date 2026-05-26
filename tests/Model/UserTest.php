<?php

namespace Light\Tests\Model;

use Light\Model\User;
use Light\Tests\TestCase;

class UserTest extends TestCase
{
    private function makeUser(array $extra = []): User
    {
        $user = User::Create(array_merge([
            'username'     => 'testuser_' . uniqid(),
            'first_name'   => 'Test',
            'last_name'    => 'User',
            'join_date'    => date('Y-m-d'),
            'created_time' => date('Y-m-d H:i:s'),
        ], $extra));
        $user->save();
        return $user;
    }

    public function testCreateUser(): void
    {
        $user = $this->makeUser();

        $this->assertNotNull($user->user_id);
        $this->assertGreaterThan(0, (int) $user->user_id);
    }

    public function testReadUser(): void
    {
        $username = 'testuser_' . uniqid();
        $user = $this->makeUser(['username' => $username, 'first_name' => 'John']);

        $found = User::Get($user->user_id);

        $this->assertNotNull($found);
        $this->assertEquals($username, $found->username);
        $this->assertEquals('John', $found->first_name);
    }

    public function testUpdateUser(): void
    {
        $user = $this->makeUser(['first_name' => 'Before']);

        $user->first_name = 'After';
        $user->save();

        $found = User::Get($user->user_id);
        $this->assertEquals('After', $found->first_name);
    }

    public function testDeleteUser(): void
    {
        $user = $this->makeUser();
        $userId = $user->user_id;

        $user->delete();

        $this->assertNull(User::Get($userId));
    }

    public function testUsernameIsUnique(): void
    {
        $username = 'unique_' . uniqid();
        $this->makeUser(['username' => $username]);

        $this->expectException(\Exception::class);
        $this->makeUser(['username' => $username]);
    }
}
