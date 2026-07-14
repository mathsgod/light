<?php

namespace Light\Tests;

use Light\App;

class AppPermissionsTest extends TestCase
{
    /**
     * @param string[] $permissions
     * @param string[] $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('wildcardProvider')]
    public function testExpandPermissionsAddsWildcardOnlyForTwoOrMoreChildren(array $permissions, array $expected): void
    {
        $actual = App::expandPermissions($permissions);

        foreach ($expected as $p) {
            $this->assertContains($p, $actual, "Expected permission '$p' to be present");
        }

        $unexpected = array_diff(
            array_filter($actual, fn($p) => str_ends_with($p, ".*")),
            array_filter($expected, fn($p) => str_ends_with($p, ".*"))
        );

        foreach ($unexpected as $p) {
            $this->assertNotContains($p, $actual, "Unexpected wildcard '$p' should not be present");
        }
    }

    public static function wildcardProvider(): array
    {
        return [
            "sibling top-level adds wildcard" => [
                ["system.database", "system.mailtest"],
                ["system.*", "system.database", "system.mailtest", "system.index"],
            ],
            "nested siblings add parent wildcard" => [
                ["system.database.backup", "system.database.check"],
                ["system.database.*", "system.database.backup", "system.database.check", "system.database.index"],
            ],
            "single leaf does not add wildcard" => [
                ["system.setting"],
                ["system.setting"],
            ],
            "mixed hierarchy adds wildcards correctly" => [
                ["user.list", "user.add", "system.setting"],
                ["user.*", "user.list", "user.add", "user.index", "system.setting"],
            ],
            "explicit wildcard adds matching index" => [
                ["user.*"],
                ["user.*", "user.index"],
            ],
            "nested wildcard adds matching index" => [
                ["system.user.*"],
                ["system.user.*", "system.user.index"],
            ],
        ];
    }
}
