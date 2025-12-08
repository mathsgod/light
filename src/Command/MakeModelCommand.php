<?php

namespace Light\Command;

use Light\Code\ClassGenerator;
use Light\Code\PropertyGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:model',
    description: 'Generate a Model class from database table'
)]
class MakeModelCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the model (table name)')
            ->addOption('display', 'd', InputOption::VALUE_NONE, 'Output to console instead of file')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $org_name = $input->getArgument('name');
        $name = ucfirst($org_name);
        $display = $input->getOption('display');
        $force = $input->getOption('force');
        $cwd = getcwd();

        $path = $cwd . "/src/Model/{$name}.php";

        // Check if file exists (only when not displaying to console)
        if (!$display && file_exists($path) && !$force) {
            $io->error("Model {$name} already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }

        $adapter = \Light\Db\Adapter::Create();

        $class = new ClassGenerator($name, "Model");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Field");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\MagicField");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Type");
        $class->setExtendedClass("Light\\Model");
        $class->addAttribute("#[Type]");

        // Add $_table property if class name differs from table name
        if ($name != $org_name) {
            $p = new PropertyGenerator("_table", $org_name);
            $p->setStatic(true);
            $class->addPropertyFromGenerator($p);
        }

        // Add MagicField attributes for each column
        foreach ($adapter->getTable($org_name)->columns() as $field) {
            /** @var \Laminas\Db\Metadata\Object\ColumnObject $field */
            $type = $field->getDataType();

            $gql_type = match ($type) {
                "timestamp", "int unsigned", "int" => "Int",
                "varchar", "text", "datetime", "date", "time" => "String",
                "float", "decimal" => "Float",
                "tinyint" => "Boolean",
                default => "String"
            };

            if (!$field->isNullable()) {
                $gql_type = "{$gql_type}!";
            }

            $class->addAttribute("#[MagicField(name: \"{$field->getName()}\", outputType: \"$gql_type\")]");
        }

        $content = "<?php\n\n" . $class->generate();

        // Display to console
        if ($display) {
            $output->writeln($content);
            return Command::SUCCESS;
        }

        // Write to file
        $existed = file_exists($path);
        file_put_contents($path, $content);

        if ($existed && $force) {
            $io->warning("Model {$name} overwritten");
        } else {
            $io->success("Model {$name} created");
        }

        return Command::SUCCESS;
    }
}
