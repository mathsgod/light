[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/mathsgod/light)

# light

A lightweight PHP 8.3+ GraphQL backend framework for building admin/CMS applications. Exposes a GraphQL API (plus a few REST endpoints for file serving) backed by a custom ORM ([mathsgod/light-db](https://github.com/mathsgod/light-db)), RBAC, and a PSR-15 middleware pipeline.

The companion frontend module is [`nuxt-light`](../nuxt-light/) — a Nuxt 4 module using Quasar UI.

---

## Requirements

- PHP >= 8.3
- Composer
- MySQL / MariaDB (or any Laminas DB-supported database)

---

## Installation

```bash
composer install
```

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

### JWT Secret

A random string used to sign JWT access/refresh tokens.

```ini
JWT_SECRET=
```

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

---

## Architecture

```
index.php → Light\App::run()
  → Middleware pipeline (CORS, JWT auth, file upload)
  → Router:
      GET  /fs/{protocol}/{path}   — Flysystem file serving
      GET  /drive/{index}/{path}   — Drive/storage access
      POST /refresh_token          — JWT token refresh
      *                            — GraphQL execution
```

**Schema generation** is annotation-driven via [TheCodingMachine/GraphQLite](https://graphqlite.thecodingmachine.io/). Controllers in `src/Controller/` declare queries and mutations using PHP 8 attributes (`#[Query]`, `#[Mutation]`, `#[Type]`, etc.).

**ORM** — models live in `src/Model/` and extend `Light\Model`. The schema is defined in `db.json`. `save()` and `delete()` auto-populate audit fields (`created_time`, `updated_time`, `created_by`, `updated_by`) and write to `EventLog`.

**RBAC** — role → permission mappings bootstrap from `permissions.yml`; menus bootstrap from `menus.yml`. The `Administrators` role always has `*` (wildcard) permission.

**File storage** — file operations go through `Light\Drive` (Flysystem MountManager). Supported adapters: Local, AWS S3, Aliyun OSS, Hostlink.

---

## Directory Layout

| Path | Purpose |
|------|---------|
| `src/Controller/` | GraphQL controllers (queries & mutations) |
| `src/Model/` | ORM models, extend `Light\Model` |
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

Tests require a real DB connection (integration tests). Each test wraps in a DB transaction and rolls back in `tearDown()`.

---

## License

MIT
