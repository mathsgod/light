<?php
namespace Light\Framework\Tests\Model;

use Light\Db\Database;
use Light\Framework\Model\Config;
use Light\Framework\Model\EventLog;
use Light\Framework\Tests\TestCase;

final class PersistenceMigrationTest extends TestCase
{
    public function testAuditLogsUsePersistedIdsAndExcludeRolledBackWrites(): void
    {
        $config = Config::Create(['name'=>'transaction_config','value'=>'first']);
        Database::getDefault()->transaction(function () use ($config) {
            $config->save();
            self::assertSame(0, EventLog::Query()->count());
        });
        $insert = EventLog::Query(['class'=>Config::class,'action'=>'Insert'])->first();
        self::assertSame($config->config_id, $insert->id);
        self::assertSame('first', $insert->getTarget()['value']);
        try {
            Database::getDefault()->transaction(function () use ($config) {
                $config->value='aborted'; $config->save();
                throw new \RuntimeException('abort');
            });
            self::fail('Expected abort');
        } catch (\RuntimeException $e) { self::assertSame('abort',$e->getMessage()); }
        self::assertSame('first',Config::Get($config->config_id)->value);
        self::assertSame(1,EventLog::Query()->count());
        $config->value='second';
        $config->save();
        $update = EventLog::Query(['action'=>'Update'])->first();
        self::assertSame('first',$update->getSource()['value']);
        self::assertSame('second',$update->getTarget()['value']);
        $config->delete();
        self::assertSame(1,EventLog::Query(['action'=>'Delete'])->count());
    }

    public function testObjectBindingPreservesUndefinedAndExplicitNull(): void
    {
        $config=Config::Create(['name'=>'binding','value'=>'original']);
        $config->bind(new class {
            public string|\TheCodingMachine\GraphQLite\Undefined $name = \TheCodingMachine\GraphQLite\Undefined::VALUE;
            public string|null|\TheCodingMachine\GraphQLite\Undefined $value = null;
        });
        self::assertSame('binding',$config->name);
        self::assertNull($config->value);
    }
    public function testSchemaMetadataAndGeneratorsUsePdo(): void
    {
        $columns = \Light\Framework\Database\Sql::columns('Config');
        self::assertContains('config_id', array_column($columns, 'name'));
        foreach ([
            \Light\Framework\Command\MakeModelCommand::class,
            \Light\Framework\Command\MakeInputCommand::class,
            \Light\Framework\Command\MakeTsCommand::class,
        ] as $class) {
            $tester = new \Symfony\Component\Console\Tester\CommandTester(new $class());
            $input = ['name' => 'Config'];
            if ($class !== \Light\Framework\Command\MakeTsCommand::class) $input['--display'] = true;
            self::assertSame(0, $tester->execute($input));
            self::assertStringContainsString('name', $tester->getDisplay());
        }
    }

    public function testSchemaAdministrationUsesNewDatabaseConnection(): void
    {
        $app = new \Light\Framework\App();
        $controller = new \Light\Framework\Controller\DatabaseController();
        $field = new \Light\Framework\Input\Table\Column();
        $field->name='name'; $field->type='varchar'; $field->length=50; $field->nullable=true;
        try {
            self::assertTrue($controller->createDatabaseTable($app, 'migration_fixture', [$field]));
            self::assertSame('name', \Light\Framework\Database\Sql::columns('migration_fixture')[0]['name']);
            self::assertTrue($controller->addDatabaseField($app, 'migration_fixture', 'status', 'int', '', '1', false, false));
            self::assertCount(2, \Light\Framework\Database\Sql::columns('migration_fixture'));
            self::assertTrue($controller->removeDatabaseFields($app, 'migration_fixture', ['status']));
            self::assertCount(1, \Light\Framework\Database\Sql::columns('migration_fixture'));
        } finally {
            $app->getDatabase()->connection()->exec('DROP TABLE IF EXISTS migration_fixture');
            $app->getDatabase()->clearMetadata();
        }
    }
}
