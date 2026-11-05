# Migrating from Light 1.x to 2.x

Light 2.x moves the classes provided by `mathsgod/light` from the `Light` namespace to `Light\Framework`. This is a breaking change. Upgrade and test your application in a development environment before deploying it.

## Update framework class references

Add `Framework` after `Light` when referring to classes provided by this package. For example:

```php
// Light 1.x
use Light\App;
use Light\Model;
use Light\Model\User;
use Light\Controller\UserController;

// Light 2.x
use Light\Framework\App;
use Light\Framework\Model;
use Light\Framework\Model\User;
use Light\Framework\Controller\UserController;
```

Update fully qualified class names, type declarations, parent classes, interfaces, attributes and any application code generated with the previous version of the Light CLI. For example, `new \Light\App()` becomes `new \Light\Framework\App()`.

## Keep other packages' namespaces

Only classes provided by `mathsgod/light` have moved. Other packages retain their existing namespaces, including:

```php
use Light\Db\Database;
use Light\Rbac\Rbac;
use Light\GraphQL\Server;
```

Some packages share a namespace prefix. For example, this package's `Light\GraphQL\ControllerDiscovery` becomes `Light\Framework\GraphQL\ControllerDiscovery`, while `Light\GraphQL\Server` remains unchanged. Check which package provides each class before changing it; do not replace every occurrence of `Light\` globally.

## Check dynamic references

Search your code and configuration for class names stored as strings, including escaped strings such as `"Light\\Model\\User"`. Check container bindings, configuration files, reflection, serialized data and custom code generators as well as PHP imports. Update references to framework classes to their new names. Previously serialized objects containing old class names may need a separate data migration.

## Check extensions and OAuth2

Extensions that use framework classes must be updated to the new namespace and declare compatibility with the new Light major version. Existing `mathsgod/light-oauth2` 1.x releases use the old namespace and require Light 1.x; use an OAuth2 release that explicitly supports Light 2.x when one is available. The OAuth2 package's own `Light\OAuth2` namespace is separate from this framework namespace change.

## Verify the upgrade

1. Update your application's Light dependency and compatible extensions, then install dependencies with Composer.
2. Regenerate autoload files with `composer dump-autoload` if needed.
3. Run your application's test suite and check its generated GraphQL schema.
4. Exercise login, authorization, file access and other application-specific flows in a test environment before deployment.

## Update persistence dependencies

Light 2.x uses light-db 2.x and its v2 GraphQLite mapper, backed by light-entity
1.x. `App::getDatabase()` returns `Light\Db\Database`; obtain PDO through
`connection()`. Replace legacy Adapter/TableGateway calls with the new Model,
Query or PDO APIs. Queries are immutable; retain returned queries when adding
filters, ordering and pagination. See the README migration section for local
source dependencies, transaction/audit behavior and the disposable test database.
