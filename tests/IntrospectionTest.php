<?php

declare(strict_types=1);

namespace Light\Framework\Tests;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\ValidationRule;
use Laminas\Diactoros\ServerRequest;
use Light\Framework\App;
use Light\Framework\Model\Config;
use PHPUnit\Framework\Attributes\DataProvider;

final class IntrospectionTest extends TestCase
{
    private ValidationRule $originalRule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalRule = DocumentValidator::getRule(DisableIntrospection::class);
    }

    protected function tearDown(): void
    {
        // Keep tests isolated even when reproducing the former global-rule mutation.
        DocumentValidator::addRule($this->originalRule);
        parent::tearDown();
    }

    private function app(string $mode): App
    {
        if (Config::Get(['name' => 'mode']) === null) {
            Config::Create(['name' => 'mode', 'value' => $mode])->save();
        } else {
            Config::Query(['name' => 'mode'])->update(['value' => $mode]);
        }
        return new App();
    }

    private function request(string $query): ServerRequest
    {
        return (new ServerRequest())->withMethod('POST')->withParsedBody(['query' => $query]);
    }

    public static function introspectionQueries(): iterable
    {
        yield 'schema' => ['{ __schema { queryType { name } } }'];
        yield 'type' => ['{ __type(name:"String") { name } }'];
    }

    #[DataProvider('introspectionQueries')]
    public function testProductionRejectsTheFirstHttpIntrospectionRequest(string $query): void
    {
        $app = $this->app('prod');
        $response = $app->handle($this->request($query));
        $result = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('errors', $result);
        self::assertStringContainsString('introspection', $result['errors'][0]['message']);
        self::assertArrayNotHasKey('data', $result);
        self::assertSame($this->originalRule, DocumentValidator::getRule(DisableIntrospection::class));
    }

    #[DataProvider('introspectionQueries')]
    public function testDirectExecuteAlsoEnforcesProductionIntrospectionValidation(string $query): void
    {
        $result = $this->app('prod')->execute($this->request($query))->toArray();
        self::assertArrayHasKey('errors', $result);
        self::assertStringContainsString('introspection', $result['errors'][0]['message']);
        self::assertSame($this->originalRule, DocumentValidator::getRule(DisableIntrospection::class));
    }

    public function testProductionDoesNotDisableIntrospectionForTheNextDevelopmentApp(): void
    {
        $query = '{ __schema { queryType { name } } }';
        $production = $this->app('prod')->execute($this->request($query))->toArray();
        self::assertArrayHasKey('errors', $production);
        $development = $this->app('dev');
        self::assertTrue($development->isDevMode());
        $response = $development->handle($this->request($query));
        $result = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('errors', $result, json_encode($result));
        self::assertNotEmpty($result['data']['__schema']['queryType']['name']);
    }

    public function testProductionStillAllowsNormalFieldsAndTypename(): void
    {
        $result = $this->app('prod')->execute($this->request('{ __typename system { time } }'))->toArray();
        self::assertArrayNotHasKey('errors', $result, json_encode($result));
        self::assertNotEmpty($result['data']['__typename']);
        self::assertNotEmpty($result['data']['system']['time']);
    }

    public function testProductionRetainsOrdinaryQueryValidation(): void
    {
        $result = $this->app('prod')->execute($this->request('{ missingFieldForValidationTest }'))->toArray();
        self::assertArrayHasKey('errors', $result);
        self::assertStringContainsString('Cannot query field', $result['errors'][0]['message']);
    }
}
