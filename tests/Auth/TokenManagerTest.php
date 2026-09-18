<?php

namespace Light\Tests\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Light\Auth\TokenManager;
use PHPUnit\Framework\TestCase;

class TokenManagerTest extends TestCase
{
    private array $environment;
    /** @var string[] */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->environment = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->environment;
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testHs256RemainsTheDefault(): void
    {
        unset($_ENV['JWT_ALGORITHM'], $_ENV['JWT_ISSUER'], $_ENV['JWT_AUDIENCE']);
        $_ENV['JWT_SECRET'] = 'test-secret-with-sufficient-entropy';

        $token = TokenManager::encode(['id' => 42, 'type' => 'access_token']);
        $payload = TokenManager::decode($token);

        $this->assertSame('light server', $payload->iss);
        $this->assertSame('42', $payload->sub);
        $this->assertSame(42, $payload->id);
    }

    public function testRs256SignsWithKidAndPublishesUsableJwks(): void
    {
        [$privatePath, $publicPath] = $this->createRsaKeyPair();
        $_ENV['JWT_ALGORITHM'] = 'RS256';
        $_ENV['JWT_PRIVATE_KEY_PATH'] = $privatePath;
        $_ENV['JWT_PUBLIC_KEY_PATH'] = $publicPath;
        $_ENV['JWT_KEY_ID'] = 'auth-2026-09';
        $_ENV['JWT_ISSUER'] = 'https://auth.example.com';
        $_ENV['JWT_AUDIENCE'] = 'business-api';

        $token = TokenManager::encode(['id' => 7, 'type' => 'access_token']);
        $header = json_decode($this->base64UrlDecode(explode('.', $token)[0]), true);
        $payload = JWT::decode($token, JWK::parseKeySet(TokenManager::jwks()));

        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('auth-2026-09', $header['kid']);
        $this->assertSame('https://auth.example.com', $payload->iss);
        $this->assertSame('business-api', $payload->aud);
        $this->assertSame('7', $payload->sub);
        $this->assertSame('auth-2026-09', TokenManager::jwks()['keys'][0]['kid']);
    }

    /** @return array{string, string} */
    private function createRsaKeyPair(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($privateKey);
        $this->assertTrue(openssl_pkey_export($privateKey, $privatePem));
        $details = openssl_pkey_get_details($privateKey);
        $this->assertNotFalse($details);

        $privatePath = tempnam(sys_get_temp_dir(), 'light-jwt-private-');
        $publicPath = tempnam(sys_get_temp_dir(), 'light-jwt-public-');
        $this->assertNotFalse($privatePath);
        $this->assertNotFalse($publicPath);
        file_put_contents($privatePath, $privatePem);
        file_put_contents($publicPath, $details['key']);
        $this->temporaryFiles = [$privatePath, $publicPath];

        return [$privatePath, $publicPath];
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
