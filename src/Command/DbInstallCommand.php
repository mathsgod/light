<?php

namespace Light\Command;

use Light\Model\Role;
use Light\Model\User;
use Light\Model\UserRole;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:install',
    description: 'Install the database and create admin user'
)]
class DbInstallCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cwd = getcwd();

        $io->title('Database Installation');

        // Ask for admin password (hidden input)
        $password = $io->askHidden('Enter admin password');

        if (empty($password)) {
            $io->error('Password cannot be empty');
            return Command::FAILURE;
        }

        $adapter = \Light\Db\Adapter::Create();

        $io->section('Installing database schema');

        try {
            $converter = new \JsonToSql();
            $sql = $converter->convertJsonToSql('db.json');
            $adapter->getDriver()->getConnection()->execute($sql);
            $io->info('Database schema installed');
        } catch (\Exception $e) {
            $io->error('Failed to install database: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section('Creating admin user and roles');

        // Create admin user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        User::_table()->insert([
            'username' => 'admin',
            'password' => $hashed_password,
            'first_name' => 'Admin',
            'join_date' => date('Y-m-d'),
            'created_time' => date('Y-m-d H:i:s'),
        ]);
        $io->info('Admin user created');

        // Create roles
        Role::_table()->insert([
            'name' => 'Users',
            'child' => 'Administrators',
        ]);
        Role::_table()->insert([
            'name' => 'Users',
            'child' => 'Power Users',
        ]);
        $io->info('Roles created');

        // Assign admin role
        UserRole::_table()->insert([
            'user_id' => 1,
            'role' => 'Administrators',
        ]);
        $io->info('Admin role assigned');

        $io->success('Database installed successfully!');

        return Command::SUCCESS;
    }
}
