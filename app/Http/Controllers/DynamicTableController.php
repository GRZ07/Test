<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;

class DynamicTableController extends Controller
{
    /**
     * Retrieve all table names mapped to their respective models.
     */
    public function getAllTableNames()
    {
        $modelMap = [];
        $modelPath = app_path('Models');

        // Loop through all PHP files in the Models directory
        foreach (glob($modelPath . '/*.php') as $file) {
            $modelName = basename($file, '.php'); // Get the model name without the extension
            $fullClassName = "App\\Models\\$modelName"; // Construct the full namespace

            // Check if the class exists and is a model
            if (class_exists($fullClassName) && is_subclass_of($fullClassName, \Illuminate\Database\Eloquent\Model::class)) {
                // Get the table name associated with the model
                $tableName = (new $fullClassName)->getTable();
                $modelMap[$modelName] = $tableName; // Map model name to table name
            }
        }

        // Return the model-to-table mapping as JSON
        return response()->json($modelMap);
    }

    /**
     * Retrieve table data with dynamic filtering, sorting, and pagination.
     */
    public function getTableData(Request $request)
    {
        try {
            Log::info('Retrieving table data from the database.');

            $table = $request->input('table');

            if (!Schema::hasTable($table)) {
                Log::warning("Table '{$table}' not found.");
                return response()->json(['error' => 'Table not found'], 404);
            }

            $modelClass = $this->getModelForTable($table);
            Log::info('Model class found:', ['modelClass' => $modelClass]);

            if (!$modelClass) {
                Log::error("Model not found for table '{$table}'.");
                return response()->json(['error' => 'Model not found for the specified table'], 404);
            }

            // Get column names from the table and exclude certain columns
            $columns = Schema::getColumnListing($table);
            $excludedColumns = ['remember_token', 'password'];
            $columns = array_values(array_diff($columns, $excludedColumns));

            // Get column types
            $columnTypes = [];
            foreach ($columns as $column) {
                $type = Schema::getColumnType($table, $column);
                // Map Laravel's column types to generic types
                if (in_array($type, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'float', 'double', 'decimal'])) {
                    $columnTypes[$column] = 'number';
                } elseif (in_array($type, ['date', 'datetime', 'timestamp'])) {
                    $columnTypes[$column] = 'date';
                } else {
                    $columnTypes[$column] = 'string';
                }
            }

            // Eager load relationships dynamically
            $relationships = $this->getModelRelations($modelClass);
            Log::info("Eager loading relationships: " . implode(', ', $relationships));

            // Initialize relationship details
            $relationshipDetails = [];

            // Add relationships to columns list for response and determine their types
            foreach ($relationships as $relationship) {
                $relationshipCount = $relationship . '_count';
                $columns[] = $relationshipCount; // Append relationship count column
                $columnTypes[$relationshipCount] = 'number'; // Assuming count is numeric

                // Determine the type of the relationship
                $relationshipType = $this->getRelationshipType($modelClass, $relationship);
                $relationshipDetails[$relationship] = $relationshipType;
            }

            // Build the query without allowedFilters
            $query = QueryBuilder::for($modelClass)
                ->allowedSorts($columns);

            if (!empty($relationships)) {
                $query->withCount($relationships); // Eager load the relationships
            }

            // Apply search filter if provided
            if ($search = $request->input('search')) {
                // Only apply search to string and date columns
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

            // Apply custom filters if provided
            if ($request->has('filter')) {
                foreach ($request->input('filter') as $key => $filter) {
                    $columnType = $columnTypes[$key] ?? 'string';

                    if (Str::endsWith($key, '_count')) {
                        $relationshipName = Str::before($key, '_count');
                        $relationshipType = $relationshipDetails[$relationshipName] ?? null;
                    } else {
                        $relationshipType = null;
                    }

                    $this->applyFilters($query, $key, $filter, $columnType, $relationshipType);
                }
            }

            if ($request->has('relatedTo')) {
                $relatedTo = $request->input('relatedTo');
                $relationshipName = $relatedTo['relationship'] ?? null;
                $relatedId = $relatedTo['id'] ?? null;
                $fromTable = $relatedTo['fromTable'] ?? null;

                if ($relationshipName && $relatedId && $fromTable) {
                    $fromModelClass = $this->getModelForTable($fromTable);

                    if ($fromModelClass) {
                        // Get the base name of the class, e.g., 'User'
                        $fromModelName = class_basename($fromModelClass);

                        // Get the inverse relationship name, e.g., 'users'
                        $inverseRelationshipName = Str::camel(Str::plural($fromModelName));

                        // Check if the inverse relationship exists on the modelClass
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


            // Ensure that 'data' key exists in the paginated result
            $data = $query->paginate($request->input('per_page', 10));

            // If no records are found, ensure 'data' is an empty array
            if ($data->isEmpty()) {
                $data->data = [];
            }

            Log::info("Successfully retrieved data for table '{$table}'.");

            return response()->json([
                'columns' => $columns,
                'columnTypes' => $columnTypes, // Include column types in the response
                'relationshipDetails' => $relationshipDetails, // Include relationship types
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving table data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve table data'], 500);
        }
    }

    /**
     * Apply filters to the query based on filter type and value.
     */
    protected function applyFilters($query, $key, $filter, $columnType, $relationshipType)
    {
        if (Str::endsWith($key, '_count')) {
            if ($relationshipType === 'many-to-many') {
                // Handle many-to-many relationship count filters
                if (isset($filter['type']) && isset($filter['value'])) {
                    switch ($filter['type']) {
                        case 'equals':
                            $query->having($key, '=', $filter['value']);
                            break;
                        case 'greaterThan':
                            $query->having($key, '>', $filter['value']);
                            break;
                        case 'lessThan':
                            $query->having($key, '<', $filter['value']);
                            break;
                        case 'between':
                            if (isset($filter['value']['start']) && isset($filter['value']['end'])) {
                                $start = $filter['value']['start'];
                                $end = $filter['value']['end'];

                                if ($columnType === 'number') {
                                    if ($start <= $end) {
                                        $query->havingBetween($key, [$start, $end]);
                                    } else {
                                        Log::warning("'between' filter start value is greater than end value for column '{$key}'");
                                    }
                                }
                            } else {
                                Log::warning("Incomplete 'between' filter for column '{$key}'", ['filter' => $filter]);
                            }
                            break;
                        default:
                            Log::warning("Unknown filter type '{$filter['type']}' for column '{$key}'");
                            break;
                    }
                }
            } else {
                // Handle one-to-one and one-to-many relationship count filters
                if (isset($filter['type']) && isset($filter['value'])) {
                    switch ($filter['type']) {
                        case 'equals':
                            $query->having($key, '=', $filter['value']);
                            break;
                        case 'greaterThan':
                            $query->having($key, '>', $filter['value']);
                            break;
                        case 'lessThan':
                            $query->having($key, '<', $filter['value']);
                            break;
                        case 'between':
                            if (isset($filter['value']['start']) && isset($filter['value']['end'])) {
                                $start = $filter['value']['start'];
                                $end = $filter['value']['end'];

                                if ($columnType === 'number') {
                                    if ($start <= $end) {
                                        $query->havingBetween($key, [$start, $end]);
                                    } else {
                                        Log::warning("'between' filter start value is greater than end value for column '{$key}'");
                                    }
                                }
                            } else {
                                Log::warning("Incomplete 'between' filter for column '{$key}'", ['filter' => $filter]);
                            }
                            break;
                        default:
                            Log::warning("Unknown filter type '{$filter['type']}' for column '{$key}'");
                            break;
                    }
                }
            }
        } else {
            // Handle filters for non-count columns (existing logic remains unchanged)
            if (isset($filter['type']) && isset($filter['value'])) {
                $filterValue = json_encode($filter['value']); // For logging
                Log::info("Applying filter on '{$key}' with type '{$filter['type']}' and value '{$filterValue}'");

                // Determine if the column is a count column
                $isCountColumn = Str::endsWith($key, '_count');

                switch ($filter['type']) {
                    case 'contains':
                        if (!$isCountColumn) {
                            $query->where($key, 'like', '%' . $filter['value'] . '%');
                        }
                        break;
                    case 'equals':
                        if ($isCountColumn) {
                            $query->having($key, '=', $filter['value']);
                        } else {
                            $query->where($key, '=', $filter['value']);
                        }
                        break;
                    case 'greaterThan':
                        if ($isCountColumn) {
                            $query->having($key, '>', $filter['value']);
                        } else {
                            $query->where($key, '>', $filter['value']);
                        }
                        break;
                    case 'lessThan':
                        if ($isCountColumn) {
                            $query->having($key, '<', $filter['value']);
                        } else {
                            $query->where($key, '<', $filter['value']);
                        }
                        break;
                    case 'after':
                        if ($columnType === 'date') {
                            $query->whereDate($key, '>', $filter['value']);
                        }
                        break;
                    case 'before':
                        if ($columnType === 'date') {
                            $query->whereDate($key, '<', $filter['value']);
                        }
                        break;
                    case 'between':
                        if (isset($filter['value']['start']) && isset($filter['value']['end'])) {
                            $start = $filter['value']['start'];
                            $end = $filter['value']['end'];

                            if ($columnType === 'date') {
                                // Validate date formats
                                if ($this->isValidDate($start) && $this->isValidDate($end)) {
                                    if (strtotime($start) <= strtotime($end)) {
                                        if ($isCountColumn) {
                                            // Using havingBetween for 'between' on count columns
                                            $query->havingBetween($key, [$start, $end]);
                                        } else {
                                            $query->whereBetween($key, [$start, $end]);
                                        }
                                    } else {
                                        Log::warning("'between' filter start date is after end date for column '{$key}'");
                                    }
                                } else {
                                    Log::warning("Invalid date format in 'between' filter for column '{$key}'", ['filter' => $filter]);
                                }
                            } elseif ($columnType === 'number') {
                                // Validate numerical values
                                if (is_numeric($start) && is_numeric($end)) {
                                    if ($start <= $end) {
                                        if ($isCountColumn) {
                                            $query->havingBetween($key, [$start, $end]);
                                        } else {
                                            $query->whereBetween($key, [$start, $end]);
                                        }
                                    } else {
                                        Log::warning("'between' filter start value is greater than end value for column '{$key}'");
                                    }
                                } else {
                                    Log::warning("Invalid numeric format in 'between' filter for column '{$key}'", ['filter' => $filter]);
                                }
                            } else {
                                Log::warning("'between' filter is not applicable for column '{$key}' of type '{$columnType}'");
                            }
                        } else {
                            Log::warning("Incomplete 'between' filter for column '{$key}'", ['filter' => $filter]);
                        }
                        break;
                    default:
                        Log::warning("Unknown filter type '{$filter['type']}' for column '{$key}'");
                        break;
                }
            } else {
                Log::warning("Incomplete filter for column '{$key}': ", ['filter' => $filter]);
            }
        }
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
     * Retrieve the corresponding model class for a given table.
     */
    protected function getModelForTable($table)
    {
        $modelMap = $this->getModelMap();
        return $modelMap[$table] ?? null;
    }

    /**
     * Map all models to their respective table names.
     */
    protected function getModelMap()
    {
        $modelMap = [];
        $modelPath = app_path('Models');

        foreach (glob($modelPath . '/*.php') as $file) {
            $modelName = basename($file, '.php');
            $fullClassName = "App\\Models\\$modelName";

            if (class_exists($fullClassName) && is_subclass_of($fullClassName, \Illuminate\Database\Eloquent\Model::class)) {
                $tableName = (new $fullClassName)->getTable();
                $modelMap[$tableName] = $fullClassName;
            }
        }
        return $modelMap;
    }

    /**
     * Retrieve all relationship methods of a model.
     */
    protected function getModelRelations($model)
    {
        $reflectionClass = new ReflectionClass($model);
        $methods = $reflectionClass->getMethods();

        $relations = [];

        foreach ($methods as $method) {
            if ($method->class == $reflectionClass->getName() && !$method->isStatic()) {
                try {
                    $returnType = $method->getReturnType();

                    if ($returnType) {
                        $returnTypeName = $returnType->getName();

                        if (
                            in_array($returnTypeName, [
                                \Illuminate\Database\Eloquent\Relations\HasOne::class,
                                \Illuminate\Database\Eloquent\Relations\HasMany::class,
                                \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
                                \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
                                \Illuminate\Database\Eloquent\Relations\MorphTo::class,
                                \Illuminate\Database\Eloquent\Relations\MorphOne::class,
                                \Illuminate\Database\Eloquent\Relations\MorphMany::class,
                                \Illuminate\Database\Eloquent\Relations\MorphToMany::class,
                                \Illuminate\Database\Eloquent\Relations\HasOneThrough::class,
                                \Illuminate\Database\Eloquent\Relations\HasManyThrough::class,
                            ])
                        ) {
                            $relations[] = $method->getName();
                        }
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
