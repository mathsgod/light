<?php
declare(strict_types=1);
namespace Light\WebAuthn;

use InvalidArgumentException;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

final class OriginPolicy
{
    public static function factory(string $rpId, ?string $configuredOrigins = null): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $configuredOrigins ??= $_ENV['WEBAUTHN_ALLOWED_ORIGINS'] ?? '';
        if (trim($configuredOrigins) === '') return $factory;
        $origins = [];
        foreach (explode(',', $configuredOrigins) as $origin) {
            $origin = trim($origin);
            $parts = parse_url($origin);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) ||
                isset($parts['user']) || isset($parts['pass']) ||
                isset($parts['query']) || isset($parts['fragment']) || ($parts['path'] ?? '') !== '' ||
                !in_array($parts['scheme'], ['https', 'http'], true)) {
                throw new InvalidArgumentException('WEBAUTHN_ALLOWED_ORIGINS requires exact scheme://host[:port] origins.');
            }
            $host = $parts['host'];
            if ($host !== $rpId && !str_ends_with($host, '.' . $rpId)) {
                throw new InvalidArgumentException('WebAuthn origin must match RP_ID or one of its subdomains.');
            }
            if ($parts['scheme'] === 'http' && ($host !== 'localhost' || $rpId !== 'localhost')) {
                throw new InvalidArgumentException('HTTP WebAuthn is only allowed for explicitly configured localhost origins.');
            }
            $origins[] = $origin;
        }
        $factory->setAllowedOrigins(array_values(array_unique($origins)), false);
        return $factory;
    }
}
