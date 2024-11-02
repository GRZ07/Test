<?php

namespace App\Services\DynamicTable;

use Illuminate\Support\Facades\Schema;

class TableStructureAnalyzer
{
    /**
     * Get the columns of the specified table, excluding certain columns.
     *
     * @param string $table
     * @return array
     */
    public function getTableColumns(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $excludedColumns = $this->getExcludedColumns();

        return array_values(array_diff($columns, $excludedColumns));
    }

    /**
     * Define columns to exclude from query results.
     *
     * @return array
     */
    public function getExcludedColumns(): array
    {
        return ['remember_token', 'password', 'updated_at', 'email_verified_at'];
    }

    /**
     * Get the types of the specified columns.
     *
     * @param string $table
     * @param array $columns
     * @return array
     */
    public function getColumnTypes(string $table, array $columns): array
    {
        $columnTypes = [];
        foreach ($columns as $column) {
            $type = Schema::getColumnType($table, $column);
            if (in_array($type, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'float', 'double', 'decimal'])) {
                $columnTypes[$column] = 'number';
            } elseif (in_array($type, ['date', 'datetime', 'timestamp'])) {
                $columnTypes[$column] = 'date';
            } else {
                $columnTypes[$column] = 'string';
            }
        }
        return $columnTypes;
    }
}
