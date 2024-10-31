<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\DynamicTable\ModelMapper;
use App\Services\DynamicTable\TableStructureAnalyzer;
use App\Services\DynamicTable\RelationshipHandler;
use App\Services\DynamicTable\QueryBuilderService;
use Illuminate\Support\Facades\Schema;

class DynamicTableService
{
    protected $modelMapper;
    protected $tableStructureAnalyzer;
    protected $relationshipHandler;
    protected $queryBuilderService;

    public function __construct(
        ModelMapper $modelMapper,
        TableStructureAnalyzer $tableStructureAnalyzer,
        RelationshipHandler $relationshipHandler,
        QueryBuilderService $queryBuilderService
    ) {
        $this->modelMapper = $modelMapper;
        $this->tableStructureAnalyzer = $tableStructureAnalyzer;
        $this->relationshipHandler = $relationshipHandler;
        $this->queryBuilderService = $queryBuilderService;
    }

    /**
     * Retrieve all model names mapped to their respective table names.
     *
     * @return array
     */
    public function getAllModelTableNames(): array
    {
        return $this->modelMapper->getAllModelTableNames();
    }

    /**
     * Retrieve table data with dynamic filtering, sorting, and pagination.
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getTableData(Request $request): array
    {
        $table = $request->input('table');

        if (!Schema::hasTable($table)) {
            Log::warning("Table '{$table}' not found.");
            throw new \Exception('Table not found', 404);
        }

        $modelClass = $this->getModelForTable($table);

        if (!$modelClass) {
            Log::error("Model not found for table '{$table}'.");
            throw new \Exception('Model not found for the specified table', 404);
        }

        // Get columns and their types
        $columns = $this->tableStructureAnalyzer->getTableColumns($table);
        $columnTypes = $this->tableStructureAnalyzer->getColumnTypes($table, $columns);

        // Get relationships
        $relationships = $this->relationshipHandler->getModelRelations($modelClass);
        $relationshipDetails = $this->relationshipHandler->getRelationshipDetails($modelClass, $relationships, $columns, $columnTypes);

        // Build query
        $query = $this->queryBuilderService->buildQuery($modelClass, $columns, $relationships);

        // Apply filters
        $this->queryBuilderService->applySearchFilter($query, $request, $columns, $columnTypes);
        $this->queryBuilderService->applyCustomFilters($query, $request, $columnTypes, $relationshipDetails);
        $this->queryBuilderService->applyRelatedToFilter($query, $request, $modelClass);

        // Paginate results
        $data = $query->paginate($request->input('per_page', 10));

        return [
            'columns' => $columns,
            'columnTypes' => $columnTypes,
            'relationshipDetails' => $relationshipDetails,
            'data' => $data,
        ];
    }

    /**
     * Get the corresponding model class for a given table.
     *
     * @param string $table
     * @return string|null
     */
    protected function getModelForTable(string $table): ?string
    {
        return $this->modelMapper->getModelForTable($table);
    }
}
