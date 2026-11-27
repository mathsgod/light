<?php

namespace Light\Framework\Database;

use GraphQL\Error\UserError;
use Light\Db\Database;
use Psr\Http\Message\StreamInterface;

final class Dump
{
    public static function export(): string
    {
        return self::run('export', ['mysqldump', '--databases', '--', $_ENV['DATABASE_DATABASE']]);
    }

    public static function import(Database $database, StreamInterface $sql): void
    {
        $input = tmpfile();
        if ($input === false) {
            throw new UserError('Could not prepare the database import.');
        }

        try {
            if ($sql->isSeekable()) {
                $sql->rewind();
            }
            while (!$sql->eof()) {
                $chunk = $sql->read(65536);
                if (($chunk === '' && !$sql->eof()) || fwrite($input, $chunk) !== strlen($chunk)) {
                    throw new UserError('Could not copy the SQL upload.');
                }
            }
            rewind($input);

            try {
                self::run('import', ['mysql', '--binary-mode', '--', $_ENV['DATABASE_DATABASE']], $input);
            } finally {
                // MySQL may have committed DDL before a later statement failed.
                $database->clearMetadata();
            }
        } finally {
            fclose($input);
        }
    }

    /** @param resource|null $input */
    private static function run(string $operation, array $arguments, $input = null): string
    {
        $credentials = tmpfile();
        $output = tmpfile();
        $errors = tmpfile();
        try {
            if ($credentials === false || $output === false || $errors === false) {
                throw new UserError("Could not prepare the database $operation.");
            }

            $credentialsPath = stream_get_meta_data($credentials)['uri'];
            $password = strtr($_ENV['DATABASE_PASSWORD'] ?? '', [
                "\\" => "\\\\", '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t',
            ]);
            $options = "[client]\npassword=\"$password\"\n";
            if (fwrite($credentials, $options) !== strlen($options) || !fflush($credentials)) {
                throw new UserError("Could not prepare the database $operation.");
            }

            $program = array_shift($arguments);
            $command = [
                $program,
                '--defaults-file=' . $credentialsPath,
                '--host=' . ($_ENV['DATABASE_HOSTNAME'] ?? 'localhost'),
                '--port=' . ($_ENV['DATABASE_PORT'] ?? '3306'),
                '--user=' . $_ENV['DATABASE_USERNAME'],
                ...$arguments,
            ];
            $process = proc_open($command, [$input ?? ['pipe', 'r'], $output, $errors], $pipes);
            if (!is_resource($process)) {
                throw new UserError("Database $operation failed.");
            }
            if ($input === null) {
                fclose($pipes[0]);
            }
            if (proc_close($process) !== 0) {
                throw new UserError("Database $operation failed.");
            }
            if ($input !== null) {
                return '';
            }

            rewind($output);
            $dump = stream_get_contents($output);
            if ($dump === false) {
                throw new UserError('Could not read the database export.');
            }
            return $dump;
        } finally {
            foreach ([$credentials, $output, $errors] as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }
}
