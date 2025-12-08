<?php

namespace Light\Command;

use Light\Code\ClassGenerator;
use Light\Code\MethodGenerator;
use Light\Code\ParameterGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:controller',
    description: 'Generate a GraphQL controller'
)]
class MakeControllerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller')
            ->addOption('display', 'd', InputOption::VALUE_NONE, 'Output to console instead of file')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = ucfirst($input->getArgument('name'));
        $display = $input->getOption('display');
        $force = $input->getOption('force');
        $cwd = getcwd();

        $path = $cwd . "/src/Controller/{$name}Controller.php";

        // Check if file exists (only when not displaying to console)
        if (!$display && file_exists($path) && !$force) {
            $io->error("Controller {$name} already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }

        $class = new ClassGenerator($name . "Controller", "Controller");
        $class->addUse("Model\\$name");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\InjectUser");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Query");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Mutation");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Right");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\Logged");
        $class->addUse("TheCodingMachine\\GraphQLite\\Annotations\\UseInputType");

        // list method
        $method = new MethodGenerator("list$name");
        $method->setReturnType("Light\\Db\\Query");
        $method->addAttribute("#[Query]");
        $method->addAttribute("#[Logged]");
        $method->setDocBlock("@return \\Model\\{$name}[]\n@param ?mixed \$filters");

        $param1 = new ParameterGenerator("filters", null, []);
        $param2 = new ParameterGenerator("sort", "?string", "");
        $param3 = new ParameterGenerator("user", "Light\\Model\\User");
        $param3->addAttribute("#[InjectUser]");
        $method->setParameters([$param1, $param2, $param3]);
        $method->setBody("return {$name}::Query()->filters(\$filters)->sort(\$sort);");
        $class->addMethodFromGenerator($method);

        // get snake_case of name
        $snake_name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        // add method
        $method = new MethodGenerator("add$name");
        $method->setReturnType("int");
        $method->addAttribute("#[Mutation]");
        $method->addAttribute("#[Logged]");
        $param1 = new ParameterGenerator("data", "Input\\$name");
        $param2 = new ParameterGenerator("user", "Light\\Model\\User");
        $param2->addAttribute("#[InjectUser]");
        $method->setParameters([$param1, $param2]);
        $method->setBody(
            <<<EOT
\$obj={$name}::Create();
\$obj->bind(\$data);
\$obj->save();
return \$obj->{$snake_name}_id;
EOT
        );
        $class->addMethodFromGenerator($method);

        // update method
        $method = new MethodGenerator("update$name");
        $method->setReturnType("bool");
        $method->addAttribute("#[Mutation]");
        $method->addAttribute("#[Logged]");
        $param1 = new ParameterGenerator("id", "int");
        $param2 = new ParameterGenerator("data", "Input\\$name");
        $param2->addAttribute("#[UseInputType(inputType: \"Update{$name}Input\")]");
        $param3 = new ParameterGenerator("user", "Light\\Model\\User");
        $param3->addAttribute("#[InjectUser]");
        $method->setParameters([$param1, $param2, $param3]);
        $method->setBody(
            <<<EOT
if(!\$obj={$name}::Get(\$id)) return false;
if(!\$obj->canUpdate(\$user)) return false;
\$obj->bind(\$data);
\$obj->save();
return true;
EOT
        );
        $class->addMethodFromGenerator($method);

        // delete method
        $method = new MethodGenerator("delete$name");
        $method->setReturnType("bool");
        $method->addAttribute("#[Mutation]");
        $method->addAttribute("#[Logged]");
        $param1 = new ParameterGenerator("id", "int");
        $param2 = new ParameterGenerator("user", "Light\\Model\\User");
        $param2->addAttribute("#[InjectUser]");
        $method->setParameters([$param1, $param2]);
        $method->setBody(
            <<<EOT
if(!\$obj={$name}::Get(\$id)) return false;
if(!\$obj->canDelete(\$user)) return false;
\$obj->delete();
return true;
EOT
        );
        $class->addMethodFromGenerator($method);

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
            $io->warning("Controller {$name} overwritten");
        } else {
            $io->success("Controller {$name} created");
        }

        return Command::SUCCESS;
    }
}
