<?php

namespace Light\Database;

use Light\App;
use Light\Database\Table;
use Psr\Http\Message\UploadedFileInterface;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;


#[Type]
class Schema
{

    #[Field]
    #[Right("system.database.events")]
    /**
     * @return mixed
     */
    public function getEvents(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->query("SHOW EVENTS")->execute();
        return iterator_to_array($result);
    }

    #[Field]
    #[Right("system.database.event")]
    /**
     * @return mixed
     */
    public function getEvent(#[Autowire] App $app, string $name)
    {
        $db = $app->getDatabase();
        $result = $db->query("SHOW CREATE EVENT $name")->execute();
        return iterator_to_array($result)[0];
    }

    #[Field]
    /**
     * @return mixed
     */
    #[Right("system.database.process")]
    public function getProcessList(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->query("SHOW FULL PROCESSLIST");
        return  iterator_to_array($result->execute());
    }



    #[Field(outputType: "[Table]")]
    #[Right("system.database.table")]
    public function getTables(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        $result = $db->query("SHOW TABLE STATUS")->execute();

        $data = [];
        foreach ($result as $row) {
            $data[] = new Table($row["Name"], $row);
        }

        return $data;
    }

    #[Field]
    #[Right("system.database.metadata")]
    public function getType(#[Autowire] App $app): string
    {
        $db = $app->getDatabase();
        $result = $db->query("SELECT @@version_comment as version_comment")->execute();
        $data = iterator_to_array($result);
        return (string)$data[0]["version_comment"];
    }

    #[Field]
    #[Right("system.database.metadata")]
    public function getSizeBytes(#[Autowire] App $app): int
    {
        $db = $app->getDatabase();
        $result = $db->query("SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = DATABASE()")->execute();
        $data = iterator_to_array($result);
        return (int)$data[0]["size"];
    }

    #[Field]
    #[Right("system.database.metadata")]
    public function getSize(#[Autowire] App $app): string
    {
        $bytes = $this->getSizeBytes($app);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / (1024 ** $power), 2, '.', ',') . ' ' . $units[(int)$power];
    }

    #[Field]
    #[Right("system.database.version")]
    public function getVersion(#[Autowire] App $app): string
    {
        $db = $app->getDatabase();
        $result = iterator_to_array($db->query("SELECT VERSION()")->execute());
        return $result[0]["VERSION()"];
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.table")]
    public function getTableStatus(#[Autowire] App $app)
    {
        $db = $app->getDatabase();
        return  iterator_to_array($db->query("SHOW TABLE STATUS")->execute());
    }

    #[Field(outputType: "mixed")]
    #[Right("system.database.table")]
    public function getTable(#[Autowire] App $app): array
    {
        $db = $app->getDatabase();
        $tables = $db->getTables();
        $data = [];
        foreach ($tables as $table) {

            $name = $table->getTable();
            $data[] = [
                "name" => $name,
                "columns" => $table->columns()->map(function (\Laminas\Db\Metadata\Object\ColumnObject $column) {
                    return [
                        "name" => $column->getName(),
                        "type" => $column->getDataType(),
                        "default" => $column->getColumnDefault(),
                        "null" => $column->isNullable(),
                        "length" => $column->getCharacterMaximumLength(),
                        //"key" => $column->getC
                        "extra" => $column->getErratas()
                    ];
                }),
            ];
        }
        return $data;
    }

    #[Field()]
    #[Right("system.database.export")]
    public function export(#[Autowire] App $app): string
    {
        $username = $_ENV["DATABASE_USERNAME"];
        $password = $_ENV["DATABASE_PASSWORD"];
        $database = $_ENV["DATABASE_DATABASE"];
        $host = $_ENV["DATABASE_HOSTNAME"];
        $port = $_ENV["DATABASE_PORT"];

        $command = "mysqldump -u $username -p$password --databases $database --host $host --port $port";

        //exec
        $output = [];
        exec($command, $output);

        return implode("\n", $output);
    }


    #[Field()]
    #[Right("system.database.import")]
    public function import(#[Autowire] App $app, UploadedFileInterface $file): bool
    {

        $username = $_ENV["DATABASE_USERNAME"];
        $password = $_ENV["DATABASE_PASSWORD"];
        $database = $_ENV["DATABASE_DATABASE"];
        $host = $_ENV["DATABASE_HOSTNAME"];
        $port = $_ENV["DATABASE_PORT"];

        $command = "mysql -u $username -p$password --database $database --host $host --port $port < " . $file->getStream()->getMetadata("uri");

        //exec
        $output = [];
        exec($command, $output);

        return true;
    }
}
