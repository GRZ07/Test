<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\QueryBuilder;
use ReflectionClass;

class DynamicTableService
{
    /**
     * Retrieve all model names mapped to their respective table names.
     *
     * @return array
     */
    public function getAllModelTableNames()
    {
        $modelMap = [];
        $modelPath = app_path('Models');

        foreach (glob($modelPath . '/*.php') as $file) {
            $modelName = basename($file, '.php');
            $fullClassName = "App\\Models\\$modelName";

            if (class_exists($fullClassName) && is_subclass_of($fullClassName, Model::class)) {
                $tableName = (new $fullClassName)->getTable();
                $modelMap[$modelName] = $tableName;
            }
        }

        return $modelMap;
    }

    /**
     * Retrieve table data with dynamic filtering, sorting, and pagination.
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getTableData(Request $request)
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
        $columns = $this->getTableColumns($table);
        $columnTypes = $this->getColumnTypes($table, $columns);

        // Get relationships
        $relationships = $this->getModelRelations($modelClass);
        $relationshipDetails = $this->getRelationshipDetails($modelClass, $relationships, $columns, $columnTypes);

        // Build query
        $query = $this->buildQuery($modelClass, $columns, $relationships);

        // Apply filters
        $this->applySearchFilter($query, $request, $columns, $columnTypes);
        $this->applyCustomFilters($query, $request, $columnTypes, $relationshipDetails);
        $this->applyRelatedToFilter($query, $request, $modelClass);

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
    protected function getModelForTable($table)
    {
        $modelMap = $this->getModelMap();
        return $modelMap[$table] ?? null;
    }

    /**
     * Map all models to their respective table names.
     *
     * @return array
     */
    protected function getModelMap()
    {
        $modelMap = [];
        $modelPath = app_path('Models');

        foreach (glob($modelPath . '/*.php') as $file) {
            $modelName = basename($file, '.php');
            $fullClassName = "App\\Models\\$modelName";

            if (class_exists($fullClassName) && is_subclass_of($fullClassName, Model::class)) {
                $tableName = (new $fullClassName)->getTable();
                $modelMap[$tableName] = $fullClassName;
            }
        }

        return $modelMap;
    }

    /**
     * Get the columns of the specified table, excluding certain columns.
     *
     * @param string $table
     * @return array
     */
    protected function getTableColumns($table)
    {
        $columns = Schema::getColumnListing($table);
        $excludedColumns = ['remember_token', 'password'];

        return array_values(array_diff($columns, $excludedColumns));
    }

    /**
     * Get the types of the specified columns.
     *
     * @param string $table
     * @param array $columns
     * @return array
     */
    protected function getColumnTypes($table, $columns)
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

    /**
     * Get relationship details and update columns and column types.
     *
     * @param string $modelClass
     * @param array $relationships
     * @param array &$columns
     * @param array &$columnTypes
     * @return array
     */
    protected function getRelationshipDetails($modelClass, $relationships, &$columns, &$columnTypes)
    {
        $relationshipDetails = [];
        foreach ($relationships as $relationship) {
            $relationshipCount = $relationship . '_count';
            $columns[] = $relationshipCount;
            $columnTypes[$relationshipCount] = 'number';
            $relationshipDetails[$relationship] = $this->getRelationshipType($modelClass, $relationship);
        }
        return $relationshipDetails;
    }

    /**
     * Build the initial query with allowed sorts and eager loading.
     *
     * @param string $modelClass
     * @param array $columns
     * @param array $relationships
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildQuery($modelClass, $columns, $relationships): QueryBuilder
    {
        $query = QueryBuilder::for($modelClass)
            ->allowedSorts($columns);

        if (!empty($relationships)) {
            $query->withCount($relationships);
        }

        return $query;
    }

    /**
     * Apply search filter to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param array $columns
     * @param array $columnTypes
     * @return void
     */
    protected function applySearchFilter($query, Request $request, $columns, $columnTypes)
    {
        if ($search = $request->input('search')) {
            $searchableColumns = array_filter($columns, function ($column) use ($columnTypes) {
                return in_array($columnTypes[$column], ['string', 'date']);
            });

            if (!empty($searchableColumns)) {
                $query->where(function ($query) use ($search, $searchableColumns) {
                    foreach ($searchableColumns as $column) {
                        $query->orWhere($column, 'like', '%' . $search . '%');
                    }
                });
            }
        }
    }

    /**
     * Apply custom filters to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param array $columnTypes
     * @param array $relationshipDetails
     * @return void
     */
    protected function applyCustomFilters($query, Request $request, $columnTypes, $relationshipDetails)
    {
        if ($request->has('filter')) {
            foreach ($request->input('filter') as $key => $filter) {
                $columnType = $columnTypes[$key] ?? 'string';

                $relationshipType = null;
                if (Str::endsWith($key, '_count')) {
                    $relationshipName = Str::before($key, '_count');
                    $relationshipType = $relationshipDetails[$relationshipName] ?? null;
                }

                $this->applyFilters($query, $key, $filter, $columnType, $relationshipType);
            }
        }
    }

    /**
     * Apply filters to the query based on filter type and value.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param array $filter
     * @param string $columnType
     * @param string|null $relationshipType
     * @return void
     */
    protected function applyFilters($query, $key, $filter, $columnType, $relationshipType)
    {
        $isCountColumn = Str::endsWith($key, '_count');

        if (!isset($filter['type'], $filter['value'])) {
            Log::warning("Incomplete filter for column '{$key}'", ['filter' => $filter]);
            return;
        }

        $filterType = $filter['type'];
        $filterValue = $filter['value'];

        if ($isCountColumn) {
            // Handle count columns
            $this->applyCountFilter($query, $key, $filterType, $filterValue, $columnType);
        } else {
            // Handle regular columns
            $this->applyRegularFilter($query, $key, $filterType, $filterValue, $columnType);
        }
    }

    /**
     * Apply filters to count columns.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param string $filterType
     * @param mixed $filterValue
     * @param string $columnType
     * @return void
     */
    protected function applyCountFilter($query, $key, $filterType, $filterValue, $columnType)
    {
        switch ($filterType) {
            case 'equals':
                $query->having($key, '=', $filterValue);
                break;
            case 'greaterThan':
                $query->having($key, '>', $filterValue);
                break;
            case 'lessThan':
                $query->having($key, '<', $filterValue);
                break;
            case 'between':
                $this->applyBetweenFilter($query, $key, $filterValue, $columnType, true);
                break;
            default:
                Log::warning("Unknown filter type '{$filterType}' for column '{$key}'");
                break;
        }
    }

    /**
     * Apply filters to regular columns.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param string $filterType
     * @param mixed $filterValue
     * @param string $columnType
     * @return void
     */
    protected function applyRegularFilter($query, $key, $filterType, $filterValue, $columnType)
    {
        switch ($filterType) {
            case 'contains':
                $query->where($key, 'like', '%' . $filterValue . '%');
                break;
            case 'equals':
                $query->where($key, '=', $filterValue);
                break;
            case 'greaterThan':
                $query->where($key, '>', $filterValue);
                break;
            case 'lessThan':
                $query->where($key, '<', $filterValue);
                break;
            case 'after':
                if ($columnType === 'date') {
                    $query->whereDate($key, '>', $filterValue);
                }
                break;
            case 'before':
                if ($columnType === 'date') {
                    $query->whereDate($key, '<', $filterValue);
                }
                break;
            case 'between':
                $this->applyBetweenFilter($query, $key, $filterValue, $columnType, false);
                break;
            default:
                Log::warning("Unknown filter type '{$filterType}' for column '{$key}'");
                break;
        }
    }

    /**
     * Apply 'between' filter to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param mixed $filterValue
     * @param string $columnType
     * @param bool $isHaving
     * @return void
     */
    protected function applyBetweenFilter($query, $key, $filterValue, $columnType, $isHaving)
    {
        if (!isset($filterValue['start'], $filterValue['end'])) {
            Log::warning("Incomplete 'between' filter for column '{$key}'", ['filter' => $filterValue]);
            return;
        }

        $start = $filterValue['start'];
        $end = $filterValue['end'];

        if ($columnType === 'date') {
            if (!$this->isValidDate($start) || !$this->isValidDate($end)) {
                Log::warning("Invalid date format in 'between' filter for column '{$key}'", ['filter' => $filterValue]);
                return;
            }
            if (strtotime($start) > strtotime($end)) {
                Log::warning("'between' filter start date is after end date for column '{$key}'");
                return;
            }
        } elseif ($columnType === 'number') {
            if (!is_numeric($start) || !is_numeric($end)) {
                Log::warning("Invalid numeric format in 'between' filter for column '{$key}'", ['filter' => $filterValue]);
                return;
            }
            if ($start > $end) {
                Log::warning("'between' filter start value is greater than end value for column '{$key}'");
                return;
            }
        } else {
            Log::warning("'between' filter is not applicable for column '{$key}' of type '{$columnType}'");
            return;
        }

        if ($isHaving) {
            $query->havingBetween($key, [$start, $end]);
        } else {
            $query->whereBetween($key, [$start, $end]);
        }
    }

    /**
     * Apply 'relatedTo' filter to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param string $modelClass
     * @return void
     */
    protected function applyRelatedToFilter($query, Request $request, $modelClass)
    {
        if ($request->has('relatedTo')) {
            $relatedTo = $request->input('relatedTo');
            $relationshipName = $relatedTo['relationship'] ?? null;
            $relatedId = $relatedTo['id'] ?? null;
            $fromTable = $relatedTo['fromTable'] ?? null;

            if ($relationshipName && $relatedId && $fromTable) {
                $fromModelClass = $this->getModelForTable($fromTable);

                if ($fromModelClass) {
                    $inverseRelationshipName = $this->getInverseRelationshipName($fromModelClass);

                    if (method_exists($modelClass, $inverseRelationshipName)) {
                        $query->whereHas($inverseRelationshipName, function ($q) use ($relatedId) {
                            $q->where('id', $relatedId);
                        });
                    } else {
                        Log::warning("Inverse relationship '{$inverseRelationshipName}' does not exist on model '{$modelClass}'");
                    }
                } else {
                    Log::warning("Model not found for table '{$fromTable}'");
                }
            } else {
                Log::warning("Invalid 'relatedTo' parameters");
            }
        }
    }

    /**
     * Get the inverse relationship name between two models.
     *
     * @param string $fromModelClass
     * @param string $modelClass
     * @return string
     */
    protected function getInverseRelationshipName($fromModelClass)
    {
        $modelClassName = class_basename($fromModelClass);
        return Str::camel(Str::plural($modelClassName));
    }

    /**
     * Validate date format.
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    protected function isValidDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Retrieve all relationship methods of a model.
     *
     * @param string $modelClass
     * @return array
     */
    protected function getModelRelations($modelClass)
    {
        $reflectionClass = new ReflectionClass($modelClass);
        $methods = $reflectionClass->getMethods();

        $relations = [];

        foreach ($methods as $method) {
            if ($method->class == $reflectionClass->getName() && !$method->isStatic()) {
                try {
                    $returnType = $method->getReturnType();

                    if ($returnType && is_subclass_of($returnType->getName(), 'Illuminate\Database\Eloquent\Relations\Relation')) {
                        $relations[] = $method->getName();
                    }
                } catch (\ReflectionException $e) {
                    // Handle exception if needed
                }
            }
        }

        return $relations;
    }

    /**
     * Determine the type of a relationship.
     *
     * @param string $modelClass
     * @param string $relationship
     * @return string
     */
    protected function getRelationshipType($modelClass, $relationship)
    {
        $model = new $modelClass();
        $relation = $model->{$relationship}();

        switch (get_class($relation)) {
            case \Illuminate\Database\Eloquent\Relations\HasOne::class:
                return 'one-to-one';
            case \Illuminate\Database\Eloquent\Relations\HasMany::class:
                return 'one-to-many';
            case \Illuminate\Database\Eloquent\Relations\BelongsTo::class:
                return 'many-to-one';
            case \Illuminate\Database\Eloquent\Relations\BelongsToMany::class:
                return 'many-to-many';
            case \Illuminate\Database\Eloquent\Relations\MorphTo::class:
                return 'morph-to';
            case \Illuminate\Database\Eloquent\Relations\MorphOne::class:
                return 'morph-one';
            case \Illuminate\Database\Eloquent\Relations\MorphMany::class:
                return 'morph-many';
            case \Illuminate\Database\Eloquent\Relations\MorphToMany::class:
                return 'morph-to-many';
            case \Illuminate\Database\Eloquent\Relations\HasOneThrough::class:
                return 'has-one-through';
            case \Illuminate\Database\Eloquent\Relations\HasManyThrough::class:
                return 'has-many-through';
            default:
                return 'unknown';
        }
    }
}

