<?php
declare(strict_types=1);
namespace Light\Tests\Auth;
use InvalidArgumentException;
use Light\WebAuthn\OriginPolicy;
use Light\WebAuthn\Serializer;
use PHPUnit\Framework\TestCase;
use Webauthn\{AuthenticatorAssertionResponse, AuthenticatorData, AuthenticatorSelectionCriteria, CollectedClientData, CredentialRecord, PublicKeyCredentialRequestOptions};
use Webauthn\CeremonyStep\{CeremonyStepManagerFactory, CheckAllowedOrigins, CheckOrigin};
use Webauthn\Exception\AuthenticatorResponseVerificationException;

final class WebAuthnOriginPolicyTest extends TestCase
{
    private function originStep(CeremonyStepManagerFactory $factory): CheckAllowedOrigins|CheckOrigin
    {
        $property = new \ReflectionProperty($factory, 'allowedOrigins');
        $origins = $property->getValue($factory);
        return $origins === null ? new CheckOrigin([]) : new CheckAllowedOrigins($origins, false);
    }
    private function checkOrigin(string $origin, string $rpId, string $configured): void
    {
        $clientData = new CollectedClientData('', ['type'=>'webauthn.get', 'challenge'=>'dGVzdA', 'origin'=>$origin]);
        $response = AuthenticatorAssertionResponse::create($clientData, AuthenticatorData::create('', hash('sha256', $rpId, true), chr(5), 0), '');
        $record = (new \ReflectionClass(CredentialRecord::class))->newInstanceWithoutConstructor();
        $this->originStep(OriginPolicy::factory($rpId, $configured))->process($record, $response, PublicKeyCredentialRequestOptions::create('test', $rpId), null, $rpId);
    }
    public function testExplicitLocalhostOriginIsAccepted(): void
    {
        $this->checkOrigin('http://localhost:3000', 'localhost', 'http://localhost:3000');
        self::assertTrue(true);
    }
    public function testDifferentLocalhostPortIsRejected(): void
    {
        $this->expectException(AuthenticatorResponseVerificationException::class);
        $this->checkOrigin('http://localhost:3001', 'localhost', 'http://localhost:3000');
    }
    public function testUnconfiguredLocalhostHttpIsRejected(): void
    {
        $this->expectException(AuthenticatorResponseVerificationException::class);
        $this->checkOrigin('http://localhost:3000', 'localhost', '');
    }
    public function testProductionStillRequiresHttps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OriginPolicy::factory('example.com', 'http://example.com');
    }
    public function testUnrelatedHostCannotBeWhitelisted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OriginPolicy::factory('example.com', 'https://attacker.example');
    }
    public function testHttpsConfiguredOriginIsAccepted(): void
    {
        $this->checkOrigin('https://is4.hostlink.com.hk', 'is4.hostlink.com.hk', 'https://is4.hostlink.com.hk');
        self::assertTrue(true);
    }
    public function testNullAttachmentIsOmitted(): void
    {
        self::assertArrayNotHasKey('authenticatorAttachment', Serializer::toArray(AuthenticatorSelectionCriteria::create(userVerification:'required')));
        self::assertSame('platform', Serializer::toArray(AuthenticatorSelectionCriteria::create(authenticatorAttachment:'platform'))['authenticatorAttachment']);
    }
}
