<?php

namespace Light\Tests\Auth;

use InvalidArgumentException;
use Light\Auth\AudienceRegistry;
use PHPUnit\Framework\TestCase;

class AudienceRegistryTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'light-audiences-');
        $this->assertNotFalse($path);
        $this->path = $path;
        file_put_contents($this->path, <<<'YAML'
business-api:
  permissions:
    - order.*
    - customer.read
infra-api:
  permissions:
    - server.read
YAML);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testFiltersUserPermissionsToAudienceScope(): void
    {
        $registry = new AudienceRegistry($this->path);

        $this->assertSame(
            ['customer.read', 'order.list', 'order.update'],
            $registry->filterPermissions('business-api', [
                '#users',
                'order.list',
                'order.update',
                'customer.*',
                'server.read',
            ]),
        );
    }

    public function testAdministratorWildcardIsReducedToAudiencePatterns(): void
    {
        $registry = new AudienceRegistry($this->path);

        $this->assertSame(
            ['customer.read', 'order.*'],
            $registry->filterPermissions('business-api', ['*']),
        );
    }

    public function testUnknownAudienceIsRejected(): void
    {
        $registry = new AudienceRegistry($this->path);

        $this->expectException(InvalidArgumentException::class);
        $registry->filterPermissions('unknown-api', ['*']);
    }
}
