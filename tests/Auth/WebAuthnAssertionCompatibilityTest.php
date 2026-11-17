<?php
declare(strict_types=1);

namespace Light\Framework\Tests\Auth;

use GraphQL\Error\Error;
use Laminas\Diactoros\ServerRequest;
use Light\Framework\App;
use Light\Framework\Controller\WebAuthnController;
use Light\Framework\WebAuthn\Serializer;
use ParagonIE\ConstantTime\Base64UrlSafe;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredential;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class WebAuthnAssertionCompatibilityTest extends TestCase
{
    private function assertion(string $rawId): array
    {
        return [
            'id' => Base64UrlSafe::encodeUnpadded($rawId),
            'rawId' => Base64UrlSafe::encodeUnpadded($rawId),
            'type' => 'public-key',
            'response' => [
                'authenticatorData' => Base64UrlSafe::encodeUnpadded(hash('sha256', 'example.test', true) . "\x05" . pack('N', 7)),
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded(json_encode([
                    'type' => 'webauthn.get', 'challenge' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
                    'origin' => 'https://example.test', 'crossOrigin' => false,
                ], JSON_THROW_ON_ERROR)),
                'signature' => Base64UrlSafe::encodeUnpadded('test-signature'),
                'userHandle' => Base64UrlSafe::encodeUnpadded('123'),
            ],
        ];
    }

    public function testV5DeserializationExposesBinaryRawId(): void
    {
        $rawId = "\xff\x00\xfb" . random_bytes(29);
        $credential = Serializer::create()->deserialize(json_encode($this->assertion($rawId), JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json');
        self::assertSame($rawId, $credential->rawId);
        self::assertFalse(property_exists($credential, 'id'));
    }

    public function testMissingChallengeReturnsControlledError(): void
    {
        $store = new \Light\Auth\Cache\CacheStateStore(
            new \Symfony\Component\Cache\Psr16Cache(new \Symfony\Component\Cache\Adapter\ArrayAdapter()),
            new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\FlockStore()),
        );
        $app = $this->getMockBuilder(App::class)->disableOriginalConstructor()->onlyMethods(['getAuthenticationStateStore'])->getMock();
        $app->expects(self::once())->method('getAuthenticationStateStore')->willReturn($store);
        $request = (new ServerRequest([], [], 'https://example.test/'))->withCookieParams(['light_auth_binding' => str_repeat('a', 64)]);
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Invalid challenge');
        (new WebAuthnController())->webAuthnAssertion(null, $this->assertion(random_bytes(32)), $app, $request);
    }

    public function testMalformedAssertionReturnsControlledError(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Invalid credential');
        (new WebAuthnController())->webAuthnAssertion(null, null, $this->createStub(App::class), new ServerRequest());
    }
}
