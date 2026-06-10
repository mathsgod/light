<?php

namespace Light\Tests\Model;

use Laminas\Diactoros\ServerRequest;
use Light\App;
use Light\Model\Config;
use Light\Tests\TestCase;

class ConfigTest extends TestCase
{
    protected ?App $app = null;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["SCRIPT_FILENAME"] = getcwd() . "/index.php";

        $this->getApp();
    }

    private function getApp(): App
    {
        if ($this->app === null) {
            $this->app = new App();
            $request = new ServerRequest();
            $this->app->process($request, new class implements \Psr\Http\Server\RequestHandlerInterface {
                public function handle(\Psr\Http\Message\ServerRequestInterface $r): \Psr\Http\Message\ResponseInterface
                {
                    return new \Laminas\Diactoros\Response\EmptyResponse();
                }
            });
        }
        return $this->app;
    }

    public function testValueReturnsStoredValue(): void
    {
        $name = "config_test_stored_" . uniqid();
        $cfg = Config::Create(["name" => $name, "value" => "hello"]);
        $cfg->save();

        $this->assertSame("hello", Config::Value($name));
    }

    public function testValueReturnsDefaultWhenMissing(): void
    {
        $this->assertSame("fallback", Config::Value("config_test_missing_" . uniqid(), "fallback"));
    }

    public function testValueReturnsNullWhenMissingWithoutDefault(): void
    {
        $this->assertNull(Config::Value("config_test_absent_" . uniqid()));
    }

    public function testValueTreatsEmptyStringAsMissing(): void
    {
        $name = "config_test_empty_" . uniqid();
        Config::Create(["name" => $name, "value" => ""]);

        // Empty value is treated as missing, so the default is returned
        $this->assertSame("default_for_empty", Config::Value($name, "default_for_empty"));
    }

    public function testInvalidateForcesRefetch(): void
    {
        $name = "config_test_invalidate_" . uniqid();

        // Create with value "v1"
        $cfg = Config::Create(["name" => $name, "value" => "v1"]);
        $cfg->save();
        $this->assertSame("v1", Config::Value($name));

        // Update the row directly (bypassing the model API)
        Config::_table()->update(["value" => "v2"], ["name" => $name]);

        // Cache is still warm, so we should see the stale "v1"
        $this->assertSame("v1", Config::Value($name));

        // After Invalidate, the next read goes back to the DB
        Config::Invalidate($name);
        $this->assertSame("v2", Config::Value($name));
    }

    public function testCacheSurvivesAcrossCalls(): void
    {
        $name = "config_test_persist_" . uniqid();
        $cfg = Config::Create(["name" => $name, "value" => "persistent"]);
        $cfg->save();

        // First call populates the cache
        $this->assertSame("persistent", Config::Value($name));

        // Mutate the DB out from under the cache
        Config::_table()->update(["value" => "mutated"], ["name" => $name]);

        // Second call returns the cached value, not the DB row
        $this->assertSame("persistent", Config::Value($name));
    }
}
