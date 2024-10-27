<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Illuminate\Support\Facades\Validator;

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

            // Add relationships to columns list for response
            foreach ($relationships as $relationship) {
                $relationshipCount = $relationship . '_count';
                $columns[] = $relationshipCount; // Append relationship count column
                $columnTypes[$relationshipCount] = 'number'; // Assuming count is numeric
            }

            // Build the query without allowedFilters
            $query = QueryBuilder::for($modelClass)
                ->allowedSorts($columns); // Removed allowedFilters

            if (!empty($relationships)) {
                $query->withCount($relationships); // Eager load the relationships
            }

            // Apply search filter if provided
            if ($search = $request->input('search')) {
                $query->where(function ($query) use ($search, $columns) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%' . $search . '%');
                    }
                });
            }

            // Apply custom filters if provided
            if ($request->has('filter')) {
                foreach ($request->input('filter') as $key => $filter) {
                    $this->applyFilters($query, $key, $filter);
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
    protected function applyFilters($query, $key, $filter)
    {
        if (isset($filter['type']) && isset($filter['value'])) {
            $filterValue = json_encode($filter['value']); // For logging
            Log::info("Applying filter on '{$key}' with type '{$filter['type']}' and value '{$filterValue}'");

            switch ($filter['type']) {
                case 'contains':
                    $query->where($key, 'like', '%' . $filter['value'] . '%');
                    break;
                case 'equals':
                    $query->where($key, $filter['value']);
                    break;
                case 'greaterThan':
                    $query->where($key, '>', $filter['value']);
                    break;
                case 'lessThan':
                    $query->where($key, '<', $filter['value']);
                    break;
                case 'after':
                    $query->whereDate($key, '>', $filter['value']);
                    break;
                case 'before':
                    $query->whereDate($key, '<', $filter['value']);
                    break;
                case 'between':
                    if (isset($filter['value']['start']) && isset($filter['value']['end'])) {
                        $start = $filter['value']['start'];
                        $end = $filter['value']['end'];

                        // Validate date formats
                        if ($this->isValidDate($start) && $this->isValidDate($end)) {
                            if (strtotime($start) <= strtotime($end)) {
                                $query->whereBetween($key, [$start, $end]);
                            } else {
                                Log::warning("'between' filter start date is after end date for column '{$key}'");
                            }
                        } else {
                            Log::warning("Invalid date format in 'between' filter for column '{$key}'", ['filter' => $filter]);
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

    /**
     * Validate if a string is a valid date in 'Y-m-d' format.
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

                    Log::info('Method: ' . $method->getName() . ' has return type: ' . $returnType);

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
}
