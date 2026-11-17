<?php
declare(strict_types=1);

namespace Light\Framework\Tests\Fixtures;

use CBOR\{ByteStringObject, MapObject, TextStringObject};
use Light\Auth\WebAuthn\{AssertionRequest, RegistrationRequest};
use ParagonIE\ConstantTime\Base64UrlSafe as B64;

/** Test authenticator: real EC key and signatures, no mocked WebAuthn validators. */
final class PasskeyAuthenticator
{
    public string $id;
    private \OpenSSLAsymmetricKey $key;
    private string $publicKey;

    public function __construct()
    {
        $this->id = random_bytes(32);
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $details = openssl_pkey_get_details($this->key);
        $this->publicKey = "\xa5\x01\x02\x03\x26\x20\x01\x21\x58\x20" . $details['ec']['x'] . "\x22\x58\x20" . $details['ec']['y'];
    }

    /** @return array<string, mixed> */
    public function attestation(RegistrationRequest $request, string $mutation = ''): array
    {
        $clientData = json_encode([
            'type' => $mutation === 'type' ? 'webauthn.get' : 'webauthn.create',
            'challenge' => $mutation === 'challenge' ? B64::encodeUnpadded(random_bytes(32)) : $request->publicKey['challenge'],
            'origin' => $mutation === 'origin' ? 'https://evil.example.test' : 'https://example.test',
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);
        $rp = $mutation === 'rp' ? 'evil.test' : 'example.test';
        $flags = match ($mutation) { 'uv' => "\x41", 'up' => "\x44", default => "\x45" };
        $key = $mutation === 'algorithm' ? str_replace("\x03\x26", "\x03\x27", $this->publicKey) : $this->publicKey;
        $authData = hash('sha256', $rp, true) . $flags . pack('N', 0) . str_repeat("\x00", 16) . pack('n', strlen($this->id)) . $this->id . $key;
        $object = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));
        $rawId = $mutation === 'rawId' ? random_bytes(32) : $this->id;
        return [
            'id' => B64::encodeUnpadded($rawId), 'rawId' => B64::encodeUnpadded($rawId), 'type' => 'public-key',
            'response' => [
                'clientDataJSON' => B64::encodeUnpadded($clientData),
                'attestationObject' => B64::encodeUnpadded((string) $object),
                'transports' => ['internal'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function assertion(AssertionRequest $request, string $userHandle): array
    {
        $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => $request->publicKey['challenge'], 'origin' => 'https://example.test', 'crossOrigin' => false], JSON_THROW_ON_ERROR);
        $authData = hash('sha256', 'example.test', true) . "\x05" . pack('N', 1);
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);
        return [
            'id' => B64::encodeUnpadded($this->id), 'rawId' => B64::encodeUnpadded($this->id), 'type' => 'public-key',
            'response' => [
                'clientDataJSON' => B64::encodeUnpadded($clientData), 'authenticatorData' => B64::encodeUnpadded($authData),
                'signature' => B64::encodeUnpadded($signature), 'userHandle' => B64::encodeUnpadded($userHandle),
            ],
        ];
    }
}
