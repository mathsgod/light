<?php

namespace Light\Tests\Auth;

use Light\Model\Config;
use Light\Model\User;
use Light\Model\UserLog;
use Light\Tests\TestCase;

class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "PHPUnit";
    }

    private function createUser(string $username, string $password): User
    {
        $user = User::Create([
            "username" => $username,
            "first_name" => "Test",
            "email" => $username . "@test.local",
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "join_date" => date("Y-m-d"),
            "status" => 0,
            "language" => "en",
            "password_dt" => date("Y-m-d H:i:s"),
        ]);
        $user->save();
        return $user;
    }

    public function testPasswordHashAndVerify(): void
    {
        $hash = password_hash("secret123", PASSWORD_DEFAULT);
        $this->assertTrue(password_verify("secret123", $hash));
        $this->assertFalse(password_verify("wrong", $hash));
    }

    public function testUserLogFailInsert(): void
    {
        $user = $this->createUser("login_fail_" . uniqid(), "pw12345");

        UserLog::_table()->insert([
            "user_id" => $user->user_id,
            "login_dt" => date("Y-m-d H:i:s"),
            "result" => "FAIL",
            "ip" => $_SERVER["REMOTE_ADDR"],
            "user_agent" => $_SERVER["HTTP_USER_AGENT"],
        ]);

        $logs = iterator_to_array(UserLog::Query(["user_id" => $user->user_id]));
        $this->assertCount(1, $logs);
        $this->assertEquals("FAIL", $logs[0]->result);
    }

    public function testUserLogSuccessInsert(): void
    {
        $user = $this->createUser("login_ok_" . uniqid(), "pw12345");

        UserLog::_table()->insert([
            "user_id" => $user->user_id,
            "login_dt" => date("Y-m-d H:i:s"),
            "result" => "SUCCESS",
            "ip" => $_SERVER["REMOTE_ADDR"],
            "user_agent" => $_SERVER["HTTP_USER_AGENT"],
            "jti" => "test-jti-" . uniqid(),
        ]);

        $logs = iterator_to_array(UserLog::Query(["user_id" => $user->user_id]));
        $this->assertCount(1, $logs);
        $this->assertEquals("SUCCESS", $logs[0]->result);
        $this->assertNotEmpty($logs[0]->jti);
    }

    public function testIsAuthLockedFalseByDefault(): void
    {
        $user = $this->createUser("lock_no_" . uniqid(), "pw12345");
        $this->assertFalse($user->isAuthLocked());
    }

    public function testIsAuthLockedAfterFailedAttempts(): void
    {
        $user = $this->createUser("lock_yes_" . uniqid(), "pw12345");

        $attempts = intval(Config::Value("auth_lockout_attempts", 5));

        for ($i = 0; $i < $attempts; $i++) {
            UserLog::_table()->insert([
                "user_id" => $user->user_id,
                "login_dt" => date("Y-m-d H:i:s"),
                "result" => "FAIL",
                "ip" => $_SERVER["REMOTE_ADDR"],
                "user_agent" => $_SERVER["HTTP_USER_AGENT"],
            ]);
        }

        $this->assertTrue($user->isAuthLocked());
    }
}
