<?php
declare(strict_types=1);

namespace Light\Framework\WebAuthn;

use Symfony\Component\Serializer\SerializerInterface;

final class Serializer
{
    public static function create(): SerializerInterface
    {
        return \Light\Auth\WebAuthn\Serializer::create();
    }

    /** @return array<string, mixed> */
    public static function toArray(object $value): array
    {
        // WebAuthn v5 uses its normalizers, including base64url binary encoding,
        // rather than JsonSerializable on options and credential records.
        $data = json_decode(self::create()->serialize($value, 'json'), true, 512, JSON_THROW_ON_ERROR);
        if (($data['authenticatorSelection']['authenticatorAttachment'] ?? null) === null) {
            unset($data['authenticatorSelection']['authenticatorAttachment']);
        }
        if ($value instanceof \Webauthn\AuthenticatorSelectionCriteria && ($data['authenticatorAttachment'] ?? null) === null) {
            unset($data['authenticatorAttachment']);
        }
        return $data;
    }
}
