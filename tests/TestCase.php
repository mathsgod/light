<?php

namespace Light\Tests;

use Light\Db\Adapter;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Adapter::Create()->beginTransaction();
    }

    protected function tearDown(): void
    {
        Adapter::Create()->rollback();
        parent::tearDown();
    }
}
