<?php

namespace Light\Framework\Tests;

use Light\Framework\App;
use Light\Framework\Extension\ExtensionInterface;

final class AppExtensionTest extends TestCase
{
    public function testExtensionRegistersWithTheApplication(): void
    {
        $app = new class extends App {
            public int $customMenuLoads = 0;

            public function getCustomMenus(): array
            {
                $this->customMenuLoads++;
                return [['label' => 'Custom', 'permission' => 'custom.menu']];
            }

            public function isFileManagerEnabled(): bool
            {
                return true;
            }
        };
        $extension = new class implements ExtensionInterface {
            public function register(App $app): void
            {
                $app->addPermissions(['extension.example']);
                $app->addMenus([['label' => 'Extension', 'to' => '/extension', 'permission' => 'extension.example']]);
                $app->getContainer()->add('extension.example', 'registered');
            }
        };

        $app->addExtension($extension);

        self::assertSame(0, $app->customMenuLoads);
        $permissions = $app->getPermissions();
        self::assertContains('extension.example', $permissions);
        self::assertContains('custom.menu', $permissions);
        $labels = array_column($app->getMenus(), 'label');
        self::assertSame('System', $labels[0]);
        self::assertSame(['Custom', 'File Manager', 'Extension'], array_slice($labels, -3));
        self::assertContains('Extension', array_column($app->getFlatMenus(), 'label'));
        self::assertSame(1, $app->customMenuLoads);
        self::assertSame('registered', $app->getContainer()->get('extension.example'));

        $this->expectException(\LogicException::class);
        $app->addExtension($extension);
    }
}
