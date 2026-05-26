<?php

namespace Light\Tests\Auth;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Light\Tests\TestCase;
use Ramsey\Uuid\Uuid;

class JwtTest extends TestCase
{
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = $_ENV['JWT_SECRET'] ?? 'ci_test_jwt_secret_key_abcdef1234567890';
    }

    private function encode(array $payload): string
    {
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    private function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }

    public function testEncodeAndDecode(): void
    {
        $jti = Uuid::uuid4()->toString();
        $payload = [
            'iss'  => 'light server',
            'jti'  => $jti,
            'iat'  => time(),
            'id'   => 42,
            'type' => 'access_token',
        ];

        $token = $this->encode($payload);
        $decoded = $this->decode($token);

        $this->assertEquals('light server', $decoded->iss);
        $this->assertEquals($jti, $decoded->jti);
        $this->assertEquals(42, $decoded->id);
        $this->assertEquals('access_token', $decoded->type);
    }

    public function testExpiredTokenThrows(): void
    {
        $payload = [
            'iss'  => 'light server',
            'iat'  => time() - 3600,
            'exp'  => time() - 1800,   // expired 30 minutes ago
            'id'   => 1,
            'type' => 'access_token',
        ];

        $token = $this->encode($payload);

        $this->expectException(ExpiredException::class);
        $this->decode($token);
    }

    public function testValidExpiryDoesNotThrow(): void
    {
        $payload = [
            'iss'  => 'light server',
            'iat'  => time(),
            'exp'  => time() + 3600,   // valid for 1 hour
            'id'   => 1,
            'type' => 'access_token',
        ];

        $token = $this->encode($payload);
        $decoded = $this->decode($token);

        $this->assertEquals(1, $decoded->id);
    }

    public function testInvalidSecretThrows(): void
    {
        $token = $this->encode(['id' => 1, 'type' => 'access_token']);

        $this->expectException(\Exception::class);
        JWT::decode($token, new Key('wrong_secret', 'HS256'));
    }

    public function testTokenWithoutExpiryNeverExpires(): void
    {
        $payload = [
            'iat'  => time() - 86400,  // issued 1 day ago, no exp
            'id'   => 5,
            'type' => 'access_token',
        ];

        $token = $this->encode($payload);
        $decoded = $this->decode($token);

        $this->assertEquals(5, $decoded->id);
    }
}
