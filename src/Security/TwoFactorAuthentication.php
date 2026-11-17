<?php
declare(strict_types=1);

namespace Light\Framework\Security;

use Light\Auth\TotpVerifier;
use Light\Auth\User;
use Light\Framework\Auth\Authentication;
use OTPHP\TOTP;
use Psr\SimpleCache\CacheInterface;

/** @deprecated Compatibility facade; authentication and enrollment use light-auth directly. */
class TwoFactorAuthentication
{
    public static int $PASS_CODE_LENGTH = 6;
    public static int $SECRET_LENGTH = 10;
    public static int $PIN_MODULO = 1000000;

    public function checkCode($secret, $code, ?int $user_id = null, ?CacheInterface $cache = null): bool
    {
        if ($user_id !== null && $cache !== null) {
            return (new TotpVerifier(Authentication::defaultStateStore($cache)))->verify(new User((string) $user_id, '', null, totpSecret: $secret), (string) $code);
        }
        return TOTP::createFromSecret($secret)->verify((string) $code);
    }

    public function getCode($secret, $time = null): string
    {
        $otp = TOTP::createFromSecret($secret);
        return $time === null ? $otp->now() : $otp->at((int) $time * 30);
    }

    public function generateSecret(): string { return TOTP::generate()->getSecret(); }
}
