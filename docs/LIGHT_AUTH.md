# light-auth integration

Framework credential login, TOTP setup/verification and WebAuthn creation/assertion now delegate to `mathsgod/light-auth`. Framework still owns the existing User/credential storage, GraphQL endpoints, audit records, sessions, access/refresh tokens, RBAC and OAuth integration.

## Install and upgrade

Existing databases must apply [`migrations/20261117_light_auth.sql`](../migrations/20261117_light_auth.sql) before enabling new TOTP enrollments. This expands the existing `User.secret` column from 16 to 128 characters for OTPHP secrets and preserves existing values. New installations use the updated `db.json`. No tables are added.

`composer install` uses the committed lock file, currently pinning `mathsgod/light-auth` to `dev-main` at `5c6c4e9`. The prototype has no stable release yet. Until a stable registry release is available, consuming applications must register its VCS repository and explicitly allow the development requirement in their **root** composer.json; Composer does not inherit dependency repositories or root stability flags:

```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/mathsgod/light-auth.git"}
  ],
  "require": {
    "mathsgod/light-auth": "dev-main"
  }
}
```

For an existing database, apply `migrations/20261117_light_auth.sql` before enabling setup. It widens **the existing** `User.secret` field from 16 to 128 characters to accommodate OTPHP's generated Base32 secrets. It preserves existing secrets; no extra tables are created. New installs use the updated `db.json`.

## Existing configuration

| Framework setting | Owner / mapping |
| --- | --- |
| `auth_lockout_scope` | `username_ip` (default) or `ip`; IP mode counts all login submissions across usernames and keeps quota after success |
| `auth_lockout_attempts` | `Light\Auth\Policy::maxAttempts` |
| `auth_lockout_duration` | Minutes converted to `Policy::windowSeconds` |
| `password_expiration` | Enables the user repository's expiry calculation |
| `password_expiration_duration` | Days added to `User.password_dt`, supplied as a Unix expiry timestamp |
| `two_factor_authentication` | Framework still blocks password login until unenrolled users complete setup |
| `access_token_expire` / `refresh_token_expire` | Unchanged Framework token/session issuance |
| Password creation/reset policy | Framework maps existing settings to `Light\Auth\PasswordPolicy` |
| `RP_ID` / `WEBAUTHN_ALLOWED_ORIGINS` | Passed to light-auth's relying-party validation |

Set exact allowed origins (comma-separated) when the browser origin differs from `https://RP_ID`, including nondefault ports. HTTP is accepted only for explicit localhost origins, e.g. `RP_ID=localhost` and `WEBAUTHN_ALLOWED_ORIGINS=http://localhost:3000`. Forwarding a client-supplied origin into this allowlist is not supported.

## GraphQL / browser compatibility

Existing `login(username,password,code)`, `my.my2FA`, `getMy2FA`, `updateMy2FA`, `app.generate2FASecret`, `updateTwoFactorAuthentication`, WebAuthn options, `webAuthnRegister` and `webAuthnAssertion` operations remain available. TOTP setup returns an `otpauth://` URI in `provisioningUri`. Clients generate the QR image locally. The previous `image` response field has been removed. Frontends do not have to send the new library's challenge tokens: the adapter remembers them server-side for the matching user/browser. The `secret` setup field remains for existing Authenticator UI; confirmation checks it against a server-generated pending setup instead of trusting arbitrary client secrets.

The HttpOnly `light_auth_binding` cookie binds setup and WebAuthn challenges to the originating browser. Requests must retain/send cookies (including cross-origin credential configuration). There is no PHP session dependency. Binding rotates after successful login and logout; old bindings are revoked for the challenge lifetime, so old pending operations cannot be completed after logout even by replaying the old cookie. The anonymous system-required TOTP setup dialog must follow a successful password proof (`setup_2fa_required`); lookup by username alone cannot start setup.

These are application adapters, not new public HTTP endpoints. Keep existing application CSRF, session and reauthentication controls around enrollment endpoints. Logged-in enrollment endpoints retain their existing session authorization; this migration does not introduce a separate UI for recent password/passkey reauthentication.

## State and behavior

Known and unknown usernames now share the same cache-based rolling limiter, including atomic concurrency protection. `UserLog` remains an audit/session log rather than the password-login lock authority. `User::isAuthLocked()` remains a legacy log-based compatibility helper and is not used to authorize login. Failed known-user password attempts still write `UserLog`; unknown users do not require a database record. Incorrect/disabled-account credentials produce the same generic login error.

The new verifier accepts the current 30-second TOTP step only (the old helper accepted adjacent steps). Successful enrollment marks its confirming step used; wait for the next code before logging in. Existing 16-character Base32 secrets remain valid. A pending second-factor password attempt remains counted until the second factor succeeds, and library expiry uses the actual expiry timestamp instead of truncating to the current calendar day.

Passkeys use discoverable credentials with required user verification and `attestation: none`. Existing numeric user handles still work for assertion; newly enrolled handles are opaque. Serialized credential records and existing uuid/IP/user-agent/timestamp metadata stay in `User.credential`. Registration checks and writes are serialized and use `Database::transaction()` with row locks and model saves, preserving audit hooks. Counter updates use the library's optimistic-concurrency repository.

`Security\TwoFactorAuthentication` remains a compatibility facade. WebAuthn serialization is provided by `Light\Auth\WebAuthn\Serializer`. OAuth's existing call to `AuthController::login()` uses the same new flow without changing its public signature.

## Shared atomic storage

The default is the existing Framework PSR-16 cache plus Symfony Flock locks, suitable for workers on one host. Applications running multiple hosts can supply a shared cache/lock implementation before handling requests:

```php
$app->setAuthenticationStateStore(
    new \Light\Auth\Cache\CacheStateStore($sharedCache, $sharedLockFactory, 'my_application_auth')
);
```

All workers must use the same namespace/backend. `light-auth` handles challenge consumption and TOTP replay protection atomically; cache failures propagate instead of silently authenticating. Framework's binding and compatibility pointers use that same store.

## Validation

Integration tests cover original GraphQL setup payloads, configured lock/expiry settings, known and unknown accounts, the system-required setup dialog, legacy secrets, TOTP replay, credential registration/storage/assertion, numeric legacy user handles, browser isolation and logout invalidation. Tests use a disposable `light_test` MySQL database and clear persistent cache between database fixtures.

```bash
PHP_BINARY=php8.3 bash tests/run-with-mysql.sh
```

## Developing the new rate-limit option

`auth_lockout_scope` selects `username_ip` or `ip`. The security settings component offers both choices. IP mode shares one rolling quota across password/passkey submissions and usernames, and successful logins do not clear it. Older light-auth installations retain default username/IP behavior and explicitly reject IP mode until the dependency is updated.

To test changes in a sibling light-auth checkout, run `bash scripts/use-local-light-auth.sh`. It symlinks the local library into vendor using a separate Composer manifest and lock. A normal `composer install` restores the version from the committed lock.
