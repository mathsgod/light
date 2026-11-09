# Migrating from Light 1.x to 2.x

Light 2.x combines two breaking changes: framework classes move to
`Light\Framework`, and persistence moves to light-db 2, backed by light-entity 1.
Upgrade application code and extensions together in a staging environment.
This guide describes the major-version branch; it does not announce a stable
Light 2.0 release.

## 1. Prepare dependencies and a rollback point

Keep a deployable copy of the previous application and its `composer.lock`, and
back up the database before applying any application-specific data or schema
migration. Review custom Models, queries, CLI generators and installed extensions.

The dependency targets for a stable deployment are:

| Package | Target |
| --- | --- |
| `mathsgod/light` | 2.x |
| `mathsgod/light-db` | 2.x |
| `mathsgod/light-db-graphqlite-mappers` | 2.x |
| `mathsgod/light-entity` | 1.x, installed through light-db |

PHP 8.3+ and PDO MySQL are required. MySQL 8.0 is the framework integration-test
baseline; schema administration is MySQL-specific. Do not infer support for every
light-entity SQL dialect from the framework's use of that package.

light-db 2.2.1 and its v2 GraphQLite mapper are published. This checkout now uses
stable light-db `^2.2.1` and mapper `^2.0` constraints and a normal installation needs no sibling repositories
or version overrides. Light framework's own major release remains a separate
release step.

Contributors can optionally use `./dev-setup.sh` to switch to sibling
`../light-db-new` and `../light-db-graphqlite-mappers` sources. Composer repositories
in a dependency are not inherited by an application: applications using custom
source overrides must configure them at the root. Remove local path repositories,
version overrides and `@dev` flags before stable deployment. Do not copy this
framework's development lock file into an unrelated application.

## 2. Rename framework references

Only classes provided by `mathsgod/light` move:

| Light 1.x | Light 2.x |
| --- | --- |
| `Light\App` | `Light\Framework\App` |
| `Light\Model` | `Light\Framework\Model` |
| `Light\Model\User` | `Light\Framework\Model\User` |
| `Light\Controller\UserController` | `Light\Framework\Controller\UserController` |
| `Light\Auth\Service` | `Light\Framework\Auth\Service` |
| `Light\GraphQL\ControllerDiscovery` | `Light\Framework\GraphQL\ControllerDiscovery` |

Update entrypoints, imports, parent classes, type declarations, attributes,
container bindings and generated application code. For example:

```php
use Light\Framework\App;
use Light\Framework\Model;

$app = new App();
```

Independent packages keep their namespaces, including `Light\Db`, `Light\Entity`,
`Light\Linq`, `Light\Rbac` and `Light\GraphQL\Server`. Application-owned namespaces
such as `Model\User` do not need to change just because their parent class moved.
Do not perform a blanket replacement of every `Light\` reference.

Also check escaped strings such as `"Light\\Model\\User"`, reflection metadata,
serialized objects and persisted class names. Historical `EventLog.class` values
can contain the old framework Model names; restoring those revisions requires a
controlled mapping to the new names. Review known classes individually and retain
a rollback plan for any persisted-name changes. No compatibility aliases or data
rewrites are applied automatically.

## 3. Replace Adapter and TableGateway usage

`App::getDatabase()` now returns `Light\Db\Database`:

```php
$database = $app->getDatabase();
$pdo = $database->connection();

$statement = $pdo->prepare('SELECT * FROM `User` WHERE user_id = ?');
$statement->execute([$userId]);
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
```

Use Models and Query for ordinary persistence:

```php
$user = User::Get($userId); // Model or null
$user = User::Create(['username' => 'mary']); // Not saved yet
$user->save();

User::Query(['user_id' => $userId])->update(['status' => 1]);
User::Query(['user_id' => $userId])->delete();
```

Replace `Adapter::Create()`, `GetAdapter()`, `_table()->insert/update/delete()`,
Laminas result `execute()/current()` chains and Laminas metadata wrappers. They
are not emulated. Bulk Query writes execute immediately and bypass Model methods,
audit hooks and already-loaded Model snapshots. Use per-Model `save()/delete()`
when those behaviors are required.

For metadata, use `$database->metadata(User::class)` or light-entity's schema
reader. Its column objects expose properties such as `name`, `type`, `nullable`
and `generated`, rather than Laminas getters. The framework's schema management
and code generators have been migrated to PDO. After application DDL, call
`$database->clearMetadata()` and obtain fresh Models.

## 4. Retain immutable Query results

The following no longer changes `$query`:

```php
$query->where(['status' => 1]);
$query->limit(20);
```

Keep the returned query instead:

```php
$query = User::Query();
$query = $query->where(['status' => 1]);
$query = $query->sort('user_id:desc')->limit(20)->offset(0);
```

Replace predicate mutation such as `$query->where->greaterThan(...)` with maps:

```php
$query = $query->where([
    'login_dt' => ['gt' => $cutoff],
    '_or' => [['result' => 'SUCCESS'], ['result' => 'FAIL']],
]);
```

Use `whereRaw('BINARY name = ?', [$name])` for trusted SQL conditions with bound
values. A SQL string is no longer accepted by `where()`. Projection/JOIN SQL
builders such as `columns()->getSqlString()` are not provided by light-db Query;
use the corresponding light-entity query APIs or parameterized PDO where needed.
Collection operations use `asEnumerable()->select(...)` rather than the old
Query `map()` method.

Custom `RegisterFilter()` callbacks must return a condition map, expression or a
new Query for the same Model and Database. They cannot mutate a Laminas predicate
or return a literal SQL string:

```php
User::RegisterFilter('adult', static fn ($age) => ['age' => ['gte' => $age]]);
```

`filters()` accepts associative maps and lists of maps combined with AND. Empty
frontend placeholders such as `filters: [{}]` are ignored. Validate existing UI
filter payloads, logical operators, literal wildcard searches and custom ordering.

## 5. Review Model lifecycle and value handling

- Basic `Get/Create/Query/save/delete` usage remains available. `Get()` returns
  null when missing. Queries return fresh Model instances rather than an identity
  map shared across reads.
- light-db keeps the exact short class name as its default table name: `User`
  maps to `User`. Use `protected static $_table = 'legacy_users';` or `#[Table]`
  to override it. The light-entity plural-table convention does not replace this
  light-db convention.
- The base constructor is final. Move custom constructor initialization into
  explicit factories or application methods; hydration bypasses constructors.
- Native JSON columns use PHP arrays/scalars and support nested reference edits.
  Pass model values to Query updates, not pre-encoded JSON strings. MariaDB JSON
  aliases reported as LONGTEXT need explicit converters/comparers.
- MySQL `TINYINT(1)` remains an integer. Dates and exact decimals follow the
  light-entity conversion rules; review code that assumed booleans or floats.
- Omitted insert fields allow database defaults; explicit null sends NULL.
  Generated IDs/defaults are populated after successful persistence.
- Ordinary Model `save()/delete()` return write counts. Primary-key/computed-column
  mutation is rejected. Replace removed `wasChanged()/getDirty()` dependencies
  explicitly; `isDirty()` checks pending differences, not last-save history.

Framework `bind()` still accepts GraphQL input objects. Uninitialized properties
and `Undefined::VALUE` are ignored. A null on an old-style nullable named property
is treated as omitted; a null in a union with `Undefined` is explicit. Check
create/update inputs where clearing a value matters.

## 6. Use the new transaction boundary

```php
$database->transaction(function () use ($user, $notification) {
    $user->name = 'Mary';
    $user->save();
    $notification->save();
});
```

Models loaded before the callback can participate if bound to this Database.
Inside the callback, `save()` and `delete()` execute immediately. Generated IDs
and defaults are available after saving, and same-connection queries see those
writes. Model writes, Query bulk writes and raw PDO writes on this connection
commit or roll back together. Writes through another Database are outside this
transaction boundary.

Callback errors roll back database writes and discard pending persistence hooks.
PHP Model state already accepted by a successful save is not rewound on rollback;
discard or reload participating Models before retrying. Nested transactions and
transactions opened directly on PDO are rejected. Outside `transaction()`,
individual Model writes remain immediate.

Framework audit records run after successful persistence, including writes inside
`Database::transaction()`. Audit insertion is a separate write after the Model transaction commits;
it is not atomic with the business data. An audit failure can be reported after
data has committed. Do not retry an entire operation blindly after a post-commit
hook failure.

## 7. Preserve GraphQL and extension integrations

List fields retain `data(limit:, offset:)` and `meta { total key name }`.
`meta.total` is the filtered count before pagination. Multiple data aliases can
request independent pages. Offset requires a limit; negative pagination is
rejected. Composite `meta.key` values are comma-separated key names.

The familiar list shape does not guarantee that every custom application's schema
is unchanged. Compare introspection output and exercise list, mutation and input
queries against the actual frontend.

Extensions must use `Light\Framework` references and declare compatibility with
the new major version. The extension's own namespace need not change. Existing
light-oauth2 1.x releases depend on Light 1.x and are not installed by this branch;
use an explicitly compatible release before enabling OAuth. Preserve the
framework's extension registration API for compatible extensions.

## 8. Validate and deploy

1. Install the selected package versions and regenerate Composer autoload files.
2. Search application code/configuration for old framework class names, legacy DB
   APIs and ignored Query return values. Review stored class-name migrations.
3. Run application tests and inspect the GraphQL schema. Check login, logout,
   refresh/revocation, RBAC, WebAuthn, user-log filters, pagination, JSON fields,
   audit records, transaction failure/retry, file access and schema tools in staging.
4. For this framework repository, use `composer test:docker`. It creates a
   disposable `light_test` MySQL fixture and clears tables between tests. Never
   point this integration suite at an application database.
5. Deploy the tested code and matching lock file together. Invalidate cached
   GraphQL schema/type mappings using the deployment's cache policy; account for
   auth/session entries if the cache is shared. Restart long-running PHP workers.
6. Check the frontend's initial page and API error responses. A successful backend
   suite does not verify an existing application's live session, configuration,
   schema or browser state.

The namespace/package upgrade does not itself apply application DDL. Review any
separate schema/data migrations before running them. To roll back, restore the
previous code and lock file, restore or reverse incompatible data/schema changes
as planned, and invalidate incompatible schema/type caches again. Rolling code
back alone does not undo database changes.
