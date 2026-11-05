<?php

namespace Light\Framework\Tests;

use Light\Framework\App;
use Light\Framework\Extension\ExtensionInterface;

final class AppExtensionTest extends TestCase
{
    public function testExtensionRegistersWithTheApplication(): void
    {
        $app = new App();
        $extension = new class implements ExtensionInterface {
            public function register(App $app): void
            {
                $app->addPermissions(['extension.example']);
                $app->addMenus([['label' => 'Extension', 'to' => '/extension', 'permission' => 'extension.example']]);
                $app->getContainer()->add('extension.example', 'registered');
            }
        };

        $app->addExtension($extension);

        self::assertContains('extension.example', $app->getPermissions());
        self::assertContains('Extension', array_column($app->getMenus(), 'label'));
        self::assertSame('registered', $app->getContainer()->get('extension.example'));

        $this->expectException(\LogicException::class);
        $app->addExtension($extension);
    }
}
