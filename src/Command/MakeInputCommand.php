<?php

namespace Light\Command;

use Laminas\Code\Generator\TypeGenerator;
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
    name: 'make:input',
    description: 'Generate an Input class for GraphQL mutations'
)]
class MakeInputCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the input (table name)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $org_name = $input->getArgument('name');
        $name = ucfirst($org_name);
        $force = $input->getOption('force');
        $cwd = getcwd();

        $path = $cwd . "/src/Input/{$name}.php";

        // Check if file exists
        if (file_exists($path) && !$force) {
            $io->error("Input {$name} already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }

        $adapter = \Light\Db\Adapter::Create();

        $class = new ClassGenerator($name, "Input");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Field");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Input");
        $class->addAttribute("#[Input(name: \"Create{$name}Input\", default: true)]");
        $class->addAttribute("#[Input(name: \"Update{$name}Input\", update: true)]");

        $table = $adapter->getTable($org_name);
        $keys = $table->getPrimaryKey();

        // Skip fields list
        $skipFields = ['created_time', 'updated_time', 'created_by', 'updated_by'];

        foreach ($table->columns() as $field) {
            /** @var \Laminas\Db\Metadata\Object\ColumnObject $field */
            $fieldName = $field->getName();

            // Skip primary keys
            if (in_array($fieldName, $keys)) {
                continue;
            }

            // Skip audit fields
            if (in_array($fieldName, $skipFields)) {
                continue;
            }

            $type = $field->getDataType();

            $php_type = match ($type) {
                "int" => "int",
                "varchar", "text" => "string",
                "float" => "float",
                "tinyint" => "bool",
                "json" => "string",
                default => "string"
            };

            $php_type = "?{$php_type}";

            $property = new PropertyGenerator($fieldName);
            $property->omitDefaultValue(true);
            $property->setType(TypeGenerator::fromTypeString($php_type));
            $property->addAttribute("#[Field]");
            $class->addPropertyFromGenerator($property);
        }

        // Write to file
        $existed = file_exists($path);
        file_put_contents($path, "<?php\n\n" . $class->generate());

        if ($existed && $force) {
            $io->warning("Input {$name} overwritten");
        } else {
            $io->success("Input {$name} created");
        }

        return Command::SUCCESS;
    }
}
