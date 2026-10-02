<?php
declare(strict_types=1);

namespace Light\Tests\Auth;

use GraphQL\Error\Error;
use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Controller\WebAuthnController;
use Light\WebAuthn\Serializer;
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

    public function testAssertionLooksUpEncodedRawIdAndRejectsExpiredChallenge(): void
    {
        $rawId = random_bytes(32);
        $source = new CredentialRecord($rawId, 'public-key', ['internal'], 'none', EmptyTrustPath::create(), Uuid::v4(), 'test-key', '123', 7);
        $controller = $this->getMockBuilder(WebAuthnController::class)->onlyMethods(['getStoredCredentials'])->getMock();
        $controller->expects(self::once())->method('getStoredCredentials')->willReturn([Serializer::toArray($source)]);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('webauthn_request_' . str_repeat('a', 32))->willReturn(null);
        $app = $this->getMockBuilder(App::class)->disableOriginalConstructor()->onlyMethods(['getCache'])->getMock();
        $app->expects(self::once())->method('getCache')->willReturn($cache);
        $request = (new ServerRequest([], [], 'https://example.test/'))->withCookieParams(['webauthn_sid' => str_repeat('a', 32)]);
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Invalid challenge');
        $controller->webAuthnAssertion(null, $this->assertion($rawId), $app, $request);
    }

    public function testUnknownCredentialReturnsControlledError(): void
    {
        $controller = $this->getMockBuilder(WebAuthnController::class)->onlyMethods(['getStoredCredentials'])->getMock();
        $controller->expects(self::once())->method('getStoredCredentials')->willReturn([]);
        $app = $this->createStub(App::class);
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Invalid credential');
        $controller->webAuthnAssertion(null, $this->assertion(random_bytes(32)), $app, new ServerRequest());
    }
}
