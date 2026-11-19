<?php
declare(strict_types=1);

namespace Light\Framework\Tests\Auth;

use Light\Framework\App;
use Light\Framework\Type\Auth;
use Light\Auth\WebAuthn\Serializer;
use Laminas\Diactoros\ServerRequest;
use ParagonIE\ConstantTime\Base64UrlSafe;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

final class WebAuthnSerializationTest extends TestCase
{
    public function testRequestOptionsEncodeChallengeAsBase64Url(): void
    {
        $challenge = "\xff\x00\xfb" . random_bytes(29);
        $options = PublicKeyCredentialRequestOptions::create($challenge, 'example.test', [], 'required');
        $data = json_decode(Serializer::create()->serialize($options, 'json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($challenge, Base64UrlSafe::decodeNoPadding($data['challenge']));
        self::assertSame('example.test', $data['rpId']);
        self::assertSame('required', $data['userVerification']);
        self::assertSame([], $data['allowCredentials']);
    }

    public function testCreationOptionsEncodeUserHandleAndChallenge(): void
    {
        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Example', 'example.test'),
            PublicKeyCredentialUserEntity::create('demo', '123', 'Demo'),
            random_bytes(32), [],
        );
        $data = json_decode(Serializer::create()->serialize($options, 'json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('123', Base64UrlSafe::decodeNoPadding($data['user']['id']));
        self::assertSame('demo', $data['user']['name']);
        self::assertSame($options->challenge, Base64UrlSafe::decodeNoPadding($data['challenge']));
    }

    public function testStoredCredentialRoundTripsWithExistingDeserializer(): void
    {
        $credential = new PublicKeyCredentialSource(random_bytes(32), 'public-key', ['internal'], 'none', EmptyTrustPath::create(), Uuid::v4(), random_bytes(64), '123', 7);
        $data = json_decode(Serializer::create()->serialize($credential, 'json'), true, 512, JSON_THROW_ON_ERROR);
        $restored = Serializer::create()->deserialize(json_encode($data, JSON_THROW_ON_ERROR), CredentialRecord::class, 'json');
        self::assertInstanceOf(CredentialRecord::class, $restored);
        self::assertSame($credential->publicKeyCredentialId, $restored->publicKeyCredentialId);
        self::assertSame($credential->credentialPublicKey, $restored->credentialPublicKey);
        self::assertSame('123', $restored->userHandle);
        self::assertSame(7, $restored->counter);
    }

}
