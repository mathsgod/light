<?php

namespace Light\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use UnexpectedValueException;

final class TokenManager
{
    public static function algorithm(): string
    {
        $algorithm = strtoupper($_ENV['JWT_ALGORITHM'] ?? 'HS256');
        if (!in_array($algorithm, ['HS256', 'RS256'], true)) {
            throw new RuntimeException("Unsupported JWT_ALGORITHM: {$algorithm}");
        }
        return $algorithm;
    }

    public static function issuer(): string
    {
        return $_ENV['JWT_ISSUER'] ?? 'light server';
    }

    public static function keyId(): string
    {
        return $_ENV['JWT_KEY_ID'] ?? 'light-default';
    }

    /** @param array<string, mixed> $payload */
    public static function encode(array $payload): string
    {
        $payload['iss'] = self::issuer();
        if (isset($payload['id']) && !isset($payload['sub'])) {
            $payload['sub'] = (string) $payload['id'];
        }
        if (!empty($_ENV['JWT_AUDIENCE']) && !isset($payload['aud'])) {
            $payload['aud'] = $_ENV['JWT_AUDIENCE'];
        }

        if (self::algorithm() === 'RS256') {
            return JWT::encode($payload, self::privateKey(), 'RS256', self::keyId());
        }

        return JWT::encode($payload, self::sharedSecret(), 'HS256');
    }

    public static function decode(string $token): object
    {
        if (self::algorithm() === 'RS256') {
            $payload = JWT::decode($token, new Key(self::publicKeyPem(), 'RS256'));
        } else {
            $payload = JWT::decode($token, new Key(self::sharedSecret(), 'HS256'));
        }

        if (!isset($payload->iss) || !hash_equals(self::issuer(), (string) $payload->iss)) {
            throw new UnexpectedValueException('Invalid JWT issuer');
        }

        if (!empty($_ENV['JWT_AUDIENCE'])) {
            $audiences = is_array($payload->aud ?? null) ? $payload->aud : [$payload->aud ?? null];
            if (!in_array($_ENV['JWT_AUDIENCE'], $audiences, true)) {
                throw new UnexpectedValueException('Invalid JWT audience');
            }
        }

        return $payload;
    }

    /**
     * @return array{keys: array<int, array<string, string>>}
     */
    public static function jwks(): array
    {
        if (self::algorithm() !== 'RS256') {
            throw new RuntimeException('JWKS is only available when JWT_ALGORITHM=RS256');
        }

        $key = openssl_pkey_get_public(self::publicKeyPem());
        if ($key === false) {
            throw new RuntimeException('Unable to read JWT public key');
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('JWT public key is not an RSA key');
        }

        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::keyId(),
            'n' => self::base64UrlEncode($details['rsa']['n']),
            'e' => self::base64UrlEncode($details['rsa']['e']),
        ]]];
    }

    public static function applicationSecret(): string
    {
        $secret = $_ENV['JWT_RESET_SECRET'] ?? $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '') {
            throw new RuntimeException('JWT_RESET_SECRET is required for password reset codes');
        }
        return $secret;
    }

    private static function sharedSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is required when JWT_ALGORITHM=HS256');
        }
        return $secret;
    }

    private static function privateKey(): \OpenSSLAsymmetricKey
    {
        $pem = self::readKey('JWT_PRIVATE_KEY_PATH');
        $key = openssl_pkey_get_private($pem, $_ENV['JWT_PRIVATE_KEY_PASSPHRASE'] ?? '');
        if ($key === false) {
            throw new RuntimeException('Unable to read JWT private key');
        }
        return $key;
    }

    private static function publicKeyPem(): string
    {
        if (!empty($_ENV['JWT_PUBLIC_KEY_PATH'])) {
            return self::readKey('JWT_PUBLIC_KEY_PATH');
        }

        $details = openssl_pkey_get_details(self::privateKey());
        if ($details === false || empty($details['key'])) {
            throw new RuntimeException('Unable to derive JWT public key');
        }
        return $details['key'];
    }

    private static function readKey(string $environmentVariable): string
    {
        $path = $_ENV[$environmentVariable] ?? '';
        if ($path === '') {
            throw new RuntimeException("{$environmentVariable} is required when JWT_ALGORITHM=RS256");
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("JWT key file is not readable: {$path}");
        }
        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException("JWT key file is empty: {$path}");
        }
        return $contents;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
