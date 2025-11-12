<?php
namespace Light;
class JsonToSql
{
    /**
     * 將 JSON 檔案轉換為 SQL CREATE TABLE 語句
     */
    public function convertJsonToSql(string $jsonFilePath): string
    {
        $json = file_get_contents($jsonFilePath);
        $tables = json_decode($json, true);

        if (!is_array($tables)) {
            throw new Exception('Invalid JSON format');
        }

        $sql = '';
        foreach ($tables as $table) {
            $sql .= $this->generateCreateTableStatement($table);
        }

        return $sql;
    }

    /**
     * 生成單個表的 CREATE TABLE 語句
     */
    private function generateCreateTableStatement(array $table): string
    {
        $tableName = $table['name'] ?? '';
        if (empty($tableName)) {
            throw new Exception('Table name is required');
        }

        $columns = $table['columns'] ?? [];
        if (empty($columns)) {
            throw new Exception("Table '{$tableName}' has no columns");
        }

        $sql = "CREATE TABLE `{$tableName}` (\n";

        // 生成欄位定義
        $columnDefinitions = [];
        foreach ($columns as $column) {
            $columnDefinitions[] = $this->generateColumnDefinition($column);
        }

        // 生成 PRIMARY KEY
        $primaryKey = $table['primary_key'] ?? [];
        if (!empty($primaryKey)) {
            $columnDefinitions[] = $this->generatePrimaryKey($primaryKey);
        }

        // 生成 UNIQUE KEY
        $uniqueKeys = $table['unique_keys'] ?? [];
        foreach ($uniqueKeys as $uniqueKey) {
            $columnDefinitions[] = $this->generateUniqueKey($uniqueKey);
        }

        // 生成 INDEX
        $indexes = $table['indexes'] ?? [];
        foreach ($indexes as $index) {
            $columnDefinitions[] = $this->generateIndex($index);
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        // 生成 ENGINE, CHARSET, COLLATE
        $engine = $table['engine'] ?? 'InnoDB';
        $charset = $table['charset'] ?? 'utf8mb4';
        $collate = $table['collate'] ?? null;

        $sql .= " ENGINE={$engine}";
        $sql .= " DEFAULT CHARSET={$charset}";
        if ($collate) {
            $sql .= " COLLATE={$collate}";
        }
        $sql .= ";\n\n";

        return $sql;
    }

    /**
     * 生成欄位定義
     */
    private function generateColumnDefinition(array $column): string
    {
        $name = $column['name'] ?? '';
        $type = strtoupper($column['type'] ?? '');

        if (empty($name) || empty($type)) {
            throw new Exception('Column name and type are required');
        }

        $def = "  `{$name}` {$type}";

        // 處理長度
        if (isset($column['length'])) {
            $def .= "({$column['length']})";
        }

        // 處理 UNSIGNED
        if ($column['unsigned'] ?? false) {
            $def .= " UNSIGNED";
        }

        // 處理 NOT NULL / NULL
        // 預設為 nullable: true，所以只有明確設定 nullable: false 才加 NOT NULL
        if (($column['nullable'] ?? true) === false) {
            $def .= " NOT NULL";
        } else {
            // 預設加 DEFAULT NULL
            $def .= " DEFAULT NULL";
        }

        // 處理 AUTO_INCREMENT
        if ($column['auto_increment'] ?? false) {
            $def .= " AUTO_INCREMENT";
        }

        // 處理 DEFAULT 值
        if (isset($column['default']) && $column['default'] !== null) {
            // 如果已經有 DEFAULT NULL，不要重複加
            if (str_contains($def, "DEFAULT NULL")) {
                $def = str_replace("DEFAULT NULL", "", $def);
            }
            
            $def .= " DEFAULT '{$column['default']}'";
        }

        return $def;
    }

    /**
     * 生成 PRIMARY KEY
     */
    private function generatePrimaryKey(array $columns): string
    {
        $columnList = implode('`, `', $columns);
        return "  PRIMARY KEY (`{$columnList}`)";
    }

    /**
     * 生成 UNIQUE KEY
     */
    private function generateUniqueKey(array $uniqueKey): string
    {
        $name = $uniqueKey['name'] ?? '';
        $columns = $uniqueKey['columns'] ?? [];
        $columnList = implode('`, `', $columns);
        return "  UNIQUE KEY `{$name}` (`{$columnList}`)";
    }

    /**
     * 生成 INDEX
     */
    private function generateIndex(array $index): string
    {
        $name = $index['name'] ?? '';
        $columns = $index['columns'] ?? [];
        $columnList = implode('`, `', $columns);
        return "  KEY `{$name}` (`{$columnList}`)";
    }
}
