<?php

namespace Light\Tests;

use Light\Db\Adapter;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // light-server scans <root>/pages via RecursiveDirectoryIterator
        // and throws if the directory is missing — ensure it exists.
        $pagesDir = getcwd() . "/pages";
        if (!is_dir($pagesDir)) {
            mkdir($pagesDir, 0777, true);
        }

        Adapter::Create()->beginTransaction();
    }

    protected function tearDown(): void
    {
        Adapter::Create()->rollback();
        // Reset static container so the next test doesn't inherit a stale
        // Auth\Service pointing at rolled-back users
        \Light\Model::SetContainer(null);
        parent::tearDown();
    }
}
