<?php
declare(strict_types=1);

namespace Light\WebAuthn;

use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

final class Serializer
{
    public static function create(): SerializerInterface
    {
        $manager = AttestationStatementSupportManager::create();
        $manager->add(NoneAttestationStatementSupport::create());
        return (new WebauthnSerializerFactory($manager))->create();
    }

    /** @return array<string, mixed> */
    public static function toArray(object $value): array
    {
        // WebAuthn v5 uses its normalizers, including base64url binary encoding,
        // rather than JsonSerializable on options and credential records.
        return json_decode(self::create()->serialize($value, 'json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
