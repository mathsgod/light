<?php
declare(strict_types=1);
namespace Light\Framework\Tests\Auth;
use InvalidArgumentException;
use Light\Auth\WebAuthn\RelyingParty;
use PHPUnit\Framework\TestCase;
use Webauthn\{AuthenticatorAssertionResponse, AuthenticatorData, CollectedClientData, CredentialRecord, PublicKeyCredentialRequestOptions};
use Webauthn\CeremonyStep\{CeremonyStepManagerFactory, CheckAllowedOrigins};
use Webauthn\Exception\AuthenticatorResponseVerificationException;

final class WebAuthnOriginPolicyTest extends TestCase
{
    private function originStep(CeremonyStepManagerFactory $factory): CheckAllowedOrigins
    {
        $property = new \ReflectionProperty($factory, 'allowedOrigins');
        $origins = $property->getValue($factory);
        return new CheckAllowedOrigins($origins, false);
    }
    private function checkOrigin(string $origin, string $rpId, array $configured): void
    {
        $clientData = new CollectedClientData('', ['type'=>'webauthn.get', 'challenge'=>'dGVzdA', 'origin'=>$origin]);
        $response = AuthenticatorAssertionResponse::create($clientData, AuthenticatorData::create('', hash('sha256', $rpId, true), chr(5), 0), '');
        $record = (new \ReflectionClass(CredentialRecord::class))->newInstanceWithoutConstructor();
        $this->originStep(RelyingParty::factory($rpId, $configured))->process($record, $response, PublicKeyCredentialRequestOptions::create('test', $rpId), null, $rpId);
    }
    public function testExplicitLocalhostOriginIsAccepted(): void
    {
        $this->checkOrigin('http://localhost:3000', 'localhost', ['http://localhost:3000']);
        self::assertTrue(true);
    }
    public function testDifferentLocalhostPortIsRejected(): void
    {
        $this->expectException(AuthenticatorResponseVerificationException::class);
        $this->checkOrigin('http://localhost:3001', 'localhost', ['http://localhost:3000']);
    }
    public function testUnconfiguredLocalhostHttpIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RelyingParty::factory('localhost', []);
    }
    public function testProductionStillRequiresHttps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RelyingParty::factory('example.com', ['http://example.com']);
    }
    public function testUnrelatedHostCannotBeWhitelisted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RelyingParty::factory('example.com', ['https://attacker.example']);
    }
    public function testHttpsConfiguredOriginIsAccepted(): void
    {
        $this->checkOrigin('https://is4.hostlink.com.hk', 'is4.hostlink.com.hk', ['https://is4.hostlink.com.hk']);
        self::assertTrue(true);
    }
}
