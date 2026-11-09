[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/mathsgod/light)

# light

A lightweight PHP 8.3+ GraphQL backend framework for building admin/CMS applications. Exposes a GraphQL API (plus a few REST endpoints for file serving) backed by a custom ORM ([mathsgod/light-db](https://github.com/mathsgod/light-db)), RBAC, and a PSR-15 middleware pipeline.

For upgrades from Light 1.x, follow the [Light 2.x migration guide](docs/migration-v2.md).

The companion frontend module is [`nuxt-light`](https://github.com/hostlink/nuxt-light) — a Nuxt 4 module using Quasar UI.

---

## Requirements

- PHP >= 8.3
- Composer
- MySQL 8.0 (the framework schema administration API is MySQL-specific)
- light-db 2.x and light-db-graphqlite-mappers 2.x, backed by light-entity 1.x

---

## Installation

```bash
composer install
```

Upgrading from Light 1.x? See the [Light 2.x migration guide](docs/migration-v2.md).

---

## Configuration

All configuration is read from a `.env` file in the project root.

### Database

```ini
DATABASE_HOSTNAME=
DATABASE_DATABASE=
DATABASE_USERNAME=
DATABASE_PASSWORD=
DATABASE_PORT=
DATABASE_CHARSET=
```

### Local OAuth 2.0 provider

OAuth requires a release of `mathsgod/light-oauth2` compatible with this major
version of Light. The existing extension requires Light 1.x and is not installed
by this branch's development setup. Applications enabling OAuth must require a
compatible extension as a runtime dependency; the configuration below describes
the integration settings for that extension.

Configure the following in the application's `.env`:

```dotenv
OAUTH_ENABLED=true
OAUTH_ISSUER=http://localhost:8888
OAUTH_RESOURCE=http://localhost:8888/api
OAUTH_PRIVATE_KEY_PATH=/absolute/path/outside-web-root/private.pem
OAUTH_PUBLIC_KEY_PATH=/absolute/path/outside-web-root/public.pem
OAUTH_ENCRYPTION_KEY=<at-least-32-random-characters>
OAUTH_SCOPES=user.list,role.list,systemvalue.list
```

Generate a separate RSA key pair for OAuth and an encryption key, for example
with `openssl genrsa -out private.pem 3072`,
`openssl rsa -in private.pem -pubout -out public.pem`, and
`openssl rand -hex 32`. Keep the key files outside the web root with permissions
`600`; never commit private keys or encryption keys. Use HTTPS outside local
development, and set `OAUTH_RESOURCE` to the actual GraphQL endpoint (including
`API_PREFIX`). Keep the encryption key stable so refresh tokens remain usable.

The `/OAuthClient` page in `nuxt-light` manages registered clients. The OAuth
discovery URL is `/.well-known/oauth-authorization-server`, with authorization,
token and revocation endpoints under `/oauth`. Authorization prompts for Light
credentials and any required 2FA, then asks for explicit consent. Consent is
bound to the request, user and browser session with a one-time CSRF token.
Only the permissions listed in `OAUTH_SCOPES` can be delegated; users must also
hold those permissions in Light RBAC.



### JWT signing

HS256 remains the default for backwards compatibility:

```ini
JWT_ALGORITHM=HS256
JWT_SECRET=replace-with-a-random-secret
```

For an Auth API that issues tokens to other services, use RS256. Only the Auth
API receives the private key:

```ini
JWT_ALGORITHM=RS256
JWT_PRIVATE_KEY_PATH=/run/secrets/light-jwt-private.pem
JWT_PUBLIC_KEY_PATH=/etc/light/light-jwt-public.pem
JWT_KEY_ID=auth-2026-09
JWT_ISSUER=https://auth.example.com
JWT_AUDIENCE=auth-api
JWT_CLIENT_ID=hostlink-app
JWT_AUDIENCES_PATH=/path/to/project/audiences.yml
JWT_RESET_SECRET=replace-with-a-separate-random-secret
```

`JWT_PUBLIC_KEY_PATH` is optional because Light can derive the public key from
the private key. `JWT_AUDIENCE` identifies tokens accepted by this Auth API and
is required before issuing tokens for other audiences. `JWT_RESET_SECRET` protects
password-reset verification codes and is required in RS256 deployments that do
not retain the legacy `JWT_SECRET`.

Generate an RSA key pair, for example:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out private.pem
openssl pkey -in private.pem -pubout -out public.pem
```

When RS256 is enabled, Light publishes the public key at:

```text
GET /.well-known/jwks.json
```

JWTs include the configured `kid` header, allowing consumers to select the
matching public key. The private key is never included in the JWKS response.

### Audience-scoped access tokens

Define which permissions may be issued to each API in `audiences.yml`.
`JWT_AUDIENCES_PATH` may point to an explicit configuration file outside the
package directory. If unset, Light checks `audiences.yml` in the current
project directory before falling back to the bundled file:

```yaml
auth-api:
  permissions:
    - '*'

business-api:
  permissions:
    - order.*
    - customer.*

infra-api:
  permissions:
    - server.*
    - deployment.*
```

An authenticated user can request a short-lived token for a registered
audience with the `createAudienceAccessToken` GraphQL mutation:

```graphql
mutation {
  createAudienceAccessToken(audience: "business-api")
}
```

The token contains only the intersection of the user's permissions and the
patterns allowed for that audience. Unknown audiences are rejected, internal
permissions beginning with `#` are omitted, and unclassified permissions are
not issued. An administrator's global `*` is reduced to the requested
audience's configured patterns.

### Timezone

```ini
TZ=Asia/Hong_Kong
```

### Google Sign-In (optional)

Install the Google API client:

```bash
composer require google/apiclient
```

Then set:

```ini
GOOGLE_CLIENT_ID=
```

### Other optional settings

```ini
API_PREFIX=       # URL prefix for the GraphQL endpoint
CORS=             # Allowed CORS origin domain
```

---

## Development Server

```bash
# Linux / macOS
sh run.sh

# Windows
run.bat
```

Both start `php -S 0.0.0.0:8888 router.php`.

---

## Database Schema

Initialize the database schema (defined in `db.json`):

```bash
php bin/light db:install
```

---

## CLI Scaffolding

```bash
php bin/light make:controller Name   # Generate a GraphQL controller
php bin/light make:model Name        # Generate an ORM model
php bin/light make:input Name        # Generate a GraphQL input type
php bin/light make:ts                # Generate TypeScript definitions from the schema
```

## Extensions

An extension groups application registrations in one place. Implement
`Light\Framework\Extension\ExtensionInterface` and register it after creating
the application and before creating a GraphQL schema or handling requests:

```php
use Light\Framework\App;
use Light\Framework\Extension\ExtensionInterface;

final class ExampleExtension implements ExtensionInterface
{
    public function register(App $app): void
    {
        $app->addPermissions(['example.view']);
        $app->addMenus([
            ['label' => 'Example', 'to' => '/example', 'permission' => 'example.view'],
        ]);
        // Register services or routes with $app->getContainer() or $app->getRouter().
    }
}

$app = new App();
$app->addExtension(new ExampleExtension());
$app->run();
```

`addExtension()` calls `register()` immediately. Registering the same extension
class twice on one application throws a `LogicException`. Declaring permissions
does not grant them to a role; use the existing RBAC configuration to grant
access. If registration throws, its exception propagates and any changes already
made by the extension are not rolled back; discard the application before retrying.

---

## Architecture

```
index.php → Light\Framework\App::run()
  → Middleware pipeline (CORS, JWT auth, file upload)
  → Router:
      GET  /fs/{protocol}/{path}   — Flysystem file serving
      GET  /drive/{index}/{path}   — Drive/storage access
      POST /refresh_token          — JWT token refresh
      *                            — GraphQL execution
```

**Schema generation** is annotation-driven via [TheCodingMachine/GraphQLite](https://graphqlite.thecodingmachine.io/). Controllers in `src/Controller/` declare queries and mutations using PHP 8 attributes (`#[Query]`, `#[Mutation]`, `#[Type]`, etc.).

**ORM** — models live in `src/Model/` and extend `Light\Framework\Model`. The schema is defined in `db.json`. `save()` and `delete()` auto-populate audit fields (`created_time`, `updated_time`, `created_by`, `updated_by`) and write to `EventLog`.

**RBAC** — role → permission mappings bootstrap from `permissions.yml`; menus bootstrap from `menus.yml`. The `Administrators` role always has `*` (wildcard) permission.

**File storage** — file operations go through `Light\Framework\Drive` (Flysystem MountManager). Supported adapters: Local, AWS S3, Aliyun OSS, Hostlink.

---

## Directory Layout

| Path | Purpose |
|------|---------|
| `src/Controller/` | GraphQL controllers (queries & mutations) |
| `src/Model/` | ORM models, extend `Light\Framework\Model` |
| `src/Input/` | GraphQL input types for mutations |
| `src/Type/` | GraphQL output types |
| `src/Command/` | Symfony Console CLI commands |
| `src/Auth/` | JWT auth & authorization logic |
| `src/Drive/` | Flysystem drive abstraction |
| `function/` | Global helper functions (auto-loaded) |
| `pages/` | Optional plain-PHP pages (return JSON) |
| `db.json` | Database schema definition |
| `menus.yml` | Hierarchical menu definitions |
| `permissions.yml` | Role → permission bootstrap mappings |

---

## Authentication Flow

1. Login via GraphQL mutation → returns `access_token` (short-lived JWT) + `refresh_token`
2. Subsequent requests send `Authorization: Bearer <access_token>`
3. Token refresh: `POST /refresh_token`
4. Optional 2FA (TOTP) and WebAuthn supported via `src/Security/`

---

## Testing

```bash
./vendor/bin/phpunit --no-coverage                          # Run all tests
./vendor/bin/phpunit --no-coverage tests/SomeTest.php       # Single test file
./vendor/bin/phpunit --no-coverage --filter testMethodName  # Single test method
./vendor/bin/phpstan analyse src/                           # Static analysis
```

Tests require the disposable `light_test` MySQL database and clear its tables
between tests. Use `composer test:docker` to create and remove the fixture
container. Direct PHPUnit commands require that fixture to be running and its
`DATABASE_*` environment variables to be configured.

---

## License

MIT

## Migrating to light-db 2

For the full upgrade sequence, examples and deployment checks, see
[the Light 1.x to 2.x migration guide](docs/migration-v2.md).

This major-version branch uses published light-db `^2.2.1` and GraphQLite mapper
`^2.0` packages, backed by light-entity 1.x. A normal `composer install` does not
require sibling source checkouts, path repositories or `@dev` flags. Contributors
can optionally run `./dev-setup.sh` to switch to sibling source packages; those
local development overrides must not be included in a stable application deploy.
The old light-oauth2 development dependency was removed because it requires
Light 1.x; applications using that optional extension need a compatible release.

`App::getDatabase()` now returns `Light\Db\Database`. Obtain PDO with
`$app->getDatabase()->connection()`. Models use the new `Get`, `Create`, `Query`,
`save` and `delete` APIs. Query objects are immutable: retain the returned query
from `where`, `filters`, `sort`, `limit` and `offset`. Replace Laminas predicate
mutation with condition arrays or parameterized `whereRaw()` calls. Legacy
`Adapter`, `_table()`, `GetAdapter()` and Laminas schema wrappers are no longer
used by the framework. Schema administration and generators use PDO metadata.

GraphQL list fields retain `data(limit:, offset:)` and `meta { total key }`;
`total` counts matching records before pagination. Composite-key metadata uses
comma-separated names. Object input binding still distinguishes Undefined from
an explicitly provided null. Audit records are written after successful Model
persistence, including immediate writes inside `Database::transaction()`; audit insertion
is a separate write after the Model transaction commits, not part of its atomic
unit. An audit failure can therefore be reported after data has committed.

The MySQL integration tests require the disposable `light_test` database.
They clear its tables between tests instead of wrapping tests in an externally
opened PDO transaction. Run `composer test:docker`; never point this suite at an
application database.
