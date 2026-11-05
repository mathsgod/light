<?php

declare(strict_types=1);
namespace Light\Framework\Tests\Code;

use Light\Framework\Code\ClassGenerator;
use PHPUnit\Framework\TestCase;

final class ClassGeneratorTest extends TestCase
{
    public function testRawAttributesCanCoexistWithNativeLaminasAttributes(): void
    {
        $generator = new ClassGenerator('Generated');
        self::assertSame($generator, $generator->addAttribute('#[Example]'));
        if (class_exists(\Laminas\Code\Generator\AttributeGenerator::class)) {
            $generator->addAttribute(\Laminas\Code\Generator\AttributeGenerator::fromName('NativeExample'));
        }
        $output = $generator->generate();
        self::assertStringContainsString('#[Example]', $output);
        self::assertStringContainsString('class Generated', $output);
        if (class_exists(\Laminas\Code\Generator\AttributeGenerator::class)) {
            self::assertStringContainsString('NativeExample', $output);
        }
    }
}
