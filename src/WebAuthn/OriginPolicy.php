<?php
declare(strict_types=1);
namespace Light\Framework\WebAuthn;

use Light\Auth\WebAuthn\RelyingParty;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

/** @deprecated Authentication uses light-auth's RelyingParty; retained for existing integrations. */
final class OriginPolicy
{
    public static function factory(string $rpId, ?string $configuredOrigins = null): CeremonyStepManagerFactory
    {
        $configuredOrigins ??= $_ENV['WEBAUTHN_ALLOWED_ORIGINS'] ?? '';
        if (trim($configuredOrigins) === '') return new CeremonyStepManagerFactory();
        return RelyingParty::factory($rpId, array_values(array_filter(array_map('trim', explode(',', $configuredOrigins)))));
    }
}
