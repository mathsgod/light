<?php

namespace Light\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:ts',
    description: 'Generate TypeScript model definition'
)]
class MakeTsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $org_name = $input->getArgument('name');

        $adapter = \Light\Db\Adapter::Create();

        $str = "export default defineLightModel({\n";
        $str .= "\tname: \"{$org_name}\",\n";
        $str .= "\tfields: {\n";

        foreach ($adapter->getTable($org_name)->columns() as $field) {
            $name = $field->getName();
            $type = $field->getDataType();

            // Convert snake_case to Title Case for label
            $label = str_replace('_', ' ', $name);
            $label = ucwords($label);

            $str .= "\t\t{$name}: {\n";
            $str .= "\t\t\tlabel: \"{$label}\",\n";
            $str .= "\t\t\tsortable: true,\n";
            $str .= "\t\t\tsearchable: true,\n";

            if ($type == "int") {
                $str .= "\t\t\tautoWidth: true,\n";
            }

            if ($type == "datetime" || $type == "date") {
                $str .= "\t\t\tsearchType: \"date\",\n";
            }

            $str .= "\t\t},\n";
        }

        $str .= "\t}\n";
        $str .= "});\n";

        $output->writeln($str);

        return Command::SUCCESS;
    }
}
