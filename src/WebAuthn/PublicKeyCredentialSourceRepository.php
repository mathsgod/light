<?php

namespace Light\WebAuthn;

use Light\Model\User;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository as WebauthnPublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class PublicKeyCredentialSourceRepository implements WebauthnPublicKeyCredentialSourceRepository
{

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {

        foreach (User::Query() as $user) {
            foreach ($user->credential as $credential) {

                $s = PublicKeyCredentialSource::createFromArray($credential["credential"]);

                if ($s->getPublicKeyCredentialId() == $publicKeyCredentialId) {

                    return $s;
                }
            }
        }
        return null;
    }

    public function  findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $sources = [];
        foreach (User::Query() as $user) {
            if (!$user->credential) continue;

            foreach ($user->credential as $credential) {
                $source = PublicKeyCredentialSource::createFromArray($credential["credential"]);
                if ($source->getUserHandle() == $publicKeyCredentialUserEntity->getId()) {
                    $sources[] = $source;
                }
            }
        }
        return $sources;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
    }
}
