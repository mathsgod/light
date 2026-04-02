# Copilot Instructions

## Project Overview

`mathsgod/light` is a PHP 8.1+ GraphQL-based admin/CMS framework. It exposes a GraphQL API (with a few REST endpoints for file serving) backed by a custom ORM, RBAC, and PSR-15 middleware pipeline. The intended frontend is a React/TypeScript SPA running on `localhost:5173`.

## Commands

```bash
# Start development server (Windows)
run.bat                          # php -S 0.0.0.0:8888 router.php

# Run tests
composer test                    # phpunit tests --verbose

# Run a single test file
./vendor/bin/phpunit tests/SomeTest.php

# Static analysis
./vendor/bin/phpstan analyse src/

# CLI scaffolding
php bin/light make:controller Name   # Generate GraphQL controller
php bin/light make:model Name        # Generate ORM model
php bin/light make:input Name        # Generate input type
php bin/light make:ts                # Generate TypeScript definitions
php bin/light db:install             # Initialize database schema
```

## Architecture

```
index.php → Light\App::run()
  → Middleware pipeline (CORS, JWT auth, upload)
  → Router:
      GET  /fs/{protocol}/{path}   — Flysystem file serving
      GET  /drive/{index}/{path}   — Drive/storage access
      POST /refresh_token          — JWT refresh
      *                            — GraphQL execution (webonyx/graphql-php)
```

**Schema generation** is annotation-driven via TheCodingMachine/GraphQLite (pulled in through `mathsgod/light-graphql`). Controllers in `src/Controller/` declare queries and mutations using PHP 8 attributes.

**Database** schema is defined in `db.json`; the ORM is `mathsgod/light-db`. Models live in `src/Model/` and extend `Light\Model`.

**RBAC** is handled by `mathsgod/light-rbac`. Role→permission mappings bootstrap from `permissions.yml`; menus bootstrap from `menus.yml` and are also stored in the `Config` table.

**Configuration** is read from a `.env` file (DATABASE_*, JWT_SECRET, GOOGLE_CLIENT_ID, TIMEZONE, API_PREFIX, CORS domain).

## Key Conventions

### GraphQL Controllers
Controllers use PHP 8 attributes — never docblock annotations:

```php
#[Query]
#[Logged]
#[Right('user.list')]
public function users(): array { ... }

#[Mutation]
#[Logged]
public function createUser(CreateUserInput $input): User { ... }
```

- `#[Query]` / `#[Mutation]` — exposes the method on the GraphQL schema
- `#[Logged]` — requires a valid JWT
- `#[Right('permission.name')]` — requires the caller to hold that RBAC permission

### Directory Layout

| Path | Purpose |
|------|---------|
| `src/Controller/` | GraphQL controllers (queries & mutations) |
| `src/Model/` | ORM models, extend `Light\Model` |
| `src/Input/` | GraphQL input types for mutations |
| `src/Type/` | GraphQL output types |
| `src/Command/` | Symfony Console CLI commands |
| `src/Auth/Service.php` | JWT auth & authorization logic |
| `src/Drive/` | Flysystem drive abstraction |
| `function/` | Global helper functions (auto-loaded) |
| `pages/` | Optional plain-PHP pages (return JSON) |
| `db.json` | Database schema definition |
| `menus.yml` | Hierarchical menu definitions |
| `permissions.yml` | Role → permission bootstrap mappings |

### Naming
- Controllers: `{Name}Controller.php` in `src/Controller/`
- Models: `{Name}.php` in `src/Model/`, class name matches table name
- Input types: `{Name}Input.php` in `src/Input/`, annotated `#[Input]`
- Output types: `{Name}.php` in `src/Type/`, annotated `#[Type]`

### ORM Pattern

```php
// Query
$users = User::Query()->where('status=1')->toArray();
$users = User::Query(['status' => 1])->toArray();

// Single record
$user = User::get($id);

// CRUD
$user->save();
$user->delete();
```

Models are annotated with `#[Type]` to double as GraphQL output types.

### Menu & Permission Definitions
- Add menu items to `menus.yml` with `permission:` keys matching RBAC permission strings.
- Add role→permission grants to `permissions.yml`.
- Both files are loaded at boot; runtime overrides are stored in the `Config` table.

### File Storage
File operations go through `Light\Drive` (Flysystem MountManager). Available adapters: Local, S3, Aliyun OSS, Hostlink. Never access the filesystem directly — use the drive abstraction.

### Authentication Flow
1. Login → `POST /` mutation returns `access_token` (short-lived JWT) + `refresh_token`
2. Subsequent requests send `Authorization: Bearer <access_token>`
3. Token refresh: `POST /refresh_token`
4. Optional 2FA (TOTP) and WebAuthn are supported via `src/Security/`
