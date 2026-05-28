# Copilot Instructions

## Project Overview

`mathsgod/light` is a PHP 8.3+ GraphQL-based admin/CMS framework. It exposes a GraphQL API (with a few REST endpoints for file serving) backed by a custom ORM, RBAC, and PSR-15 middleware pipeline. The companion frontend module is `nuxt-light` (a Nuxt 4 module using Quasar UI).

## Commands

```bash
# Start development server (Windows)
run.bat                          # php -S 0.0.0.0:8888 router.php

# Run all tests
./vendor/bin/phpunit --no-coverage

# Run a single test file
./vendor/bin/phpunit --no-coverage tests/SomeTest.php

# Run a single test method
./vendor/bin/phpunit --no-coverage --filter testMethodName

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

**RBAC** is handled by `mathsgod/light-rbac`. Role→permission mappings bootstrap from `permissions.yml`; menus bootstrap from `menus.yml`. The `Administrators` role always has `*` (wildcard) in `permissions.yml`, granting all permissions natively — never add a hard-coded `if ($user->is("Administrators")) return true` shortcut.

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
- Input types: `{Name}.php` in `src/Input/`, annotated `#[Input]`
- Output types: `{Name}.php` in `src/Type/`, annotated `#[Type]`

### ORM Pattern

```php
// Query
$users = User::Query()->where('status=1')->toArray();
$users = User::Query(['status' => 1])->toArray();

// Single record
$user = User::Get($id);

// CRUD
$user->save();
$user->delete();
```

Models are annotated with `#[Type]` to double as GraphQL output types. `save()` and `delete()` auto-populate `created_time`, `updated_time`, `created_by`, `updated_by` and write to `EventLog`.

### `bind()` for Partial Updates
`Light\Model::bind($data)` supports partial updates from GraphQL input objects:
- Uses `get_object_vars($data)` so **uninitialized** properties (fields not sent by the frontend) are skipped entirely
- **`null` values are written** — allows clearing a nullable field explicitly
- Only writes fields that exist in the DB schema (protects against injection of unknown keys)

```php
// Frontend sends: { first_name: "John" }  → only first_name updated
// Frontend sends: { last_name: null }      → last_name cleared
// Unset fields                             → DB value unchanged
$obj->bind($inputData);
$obj->save();
```

Do **not** pre-filter null values before calling `bind()` — the method handles this correctly.

### Authorization Pattern (`canDelete` / `canUpdate` / `canView`)

The base `Light\Model` returns `$by !== null` for all three (any logged-in user can act). System models override with specific rules.

**Permission split by operation type:**

| Operation | Has instance? | Where `#[Right]` lives |
|-----------|--------------|------------------------|
| `delete`, `update`, `view` | ✅ | Model instance method (`canDelete`, etc.) |
| `add`, `list`, `export` | ❌ | Controller method only |

For instance-level operations, controllers call `canDelete`/`canUpdate` and do **not** repeat `#[Right]` themselves:

```php
// Controller — no #[Right] needed for delete/update
#[Mutation]
#[Logged]
public function deleteClient(int $id, #[InjectUser] User $user): bool
{
    if (!$client = Client::Get($id)) return false;
    if (!$client->canDelete($user)) return false;
    $client->delete();
    return true;
}

// Model — #[Right] here is the single source of truth (also discovered by getPermissions())
#[Field]
#[Right('client.delete')]
#[FailWith(false)]
public function canDelete(#[InjectUser] ?User $by): bool
{
    if (!$by?->can('client.delete')) return false;  // gate for direct PHP calls
    return true;
}
```

`getPermissions()` scans `#[Right]` on all methods in `Controller/`, `Model/`, `Database/`, `Type/` — both framework and app namespaces.

### `User::can()` for Direct Authorization Checks
Use `$user->can('permission.name')` for PHP-level checks (e.g. inside `canDelete` body). It falls back to `self::$container` when GraphQLite injection is unavailable. Do not use the old `isGranted()` for direct calls — that requires `#[Autowire]` injection.

### Testing
- All tests extend `Light\Tests\TestCase` which wraps each test in a DB transaction and rolls back in `tearDown()`, also calling `Model::SetContainer(null)` to reset static state.
- Tests require a real DB connection (integration tests, not unit tests).
- `Model::$container` is static — must be reset between tests to avoid stale `Auth\Service` references.

### Type Hints (PHP 8.3)
All new code must use PHP 8.3 type hints on properties and method signatures. When overriding parent methods from `Light\Db\Model` or Laminas `RowGateway`, match or widen the parent's parameter types — never narrow them (PHP will throw a fatal error).

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

## nuxt-light (Frontend Module)

Nuxt 4 module (`@hostlink/nuxt-light`) built on Quasar UI. Lives in `nuxt-light/`.

```bash
cd nuxt-light
npm run dev          # dev server with playground
npm run test         # vitest run
npm run lint         # eslint
```

**L-components** (`src/runtime/components/L/`) are the primary UI building blocks. Key components:
- `<L-Table>` — data table wired to GraphQL; reads `canDelete`/`canUpdate` fields from the model to show/hide action buttons
- `<L-Form>` / `<L-Input>` — form components backed by FormKit + Quasar

**Vue SFC template refs**: Do not name a `ref` with the camelCase equivalent of a kebab-case component tag (e.g. `ref="qInput"` with `<q-input>`). The SFC compiler resolves the tag to the setup binding (the ref itself), causing "Invalid vnode type: undefined" at runtime.
