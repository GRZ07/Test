<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

use function PHPUnit\Framework\isEmpty;

class DynamicTableController extends Controller
{
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

    protected function getModelForTable($table)
    {
        $modelMap = $this->getModelMap();
        return $modelMap[$table] ?? null;
    }

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

            // Eager load relationships dynamically
            $relationships = $this->getModelRelations($modelClass);
            Log::info("Eager loading relationships: " . implode(', ', $relationships));

            // Add relationships to columns list for response
            foreach ($relationships as $relationship) {
                $columns[] = $relationship.'_count'; // Append relationship names to columns
            }

            // Build the query with dynamic filters and sorting
            $query = QueryBuilder::for($modelClass)
                ->allowedFilters($columns)
                ->allowedSorts($columns);

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

            $data = $query->paginate($request->input('per_page', 10));
            Log::info("Successfully retrieved data for table '{$table}'.");

            return response()->json(['columns' => $columns, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Error retrieving table data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve table data'], 500);
        }
    }


    function getModelRelations($model)
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

    protected function applyFilters($query, $key, $filter)
    {
        if (isset($filter['type']) && isset($filter['value'])) {
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
                    $query->where($key, '>', $filter['value']);
                    break;
                case 'before':
                    $query->where($key, '<', $filter['value']);
                    break;
            }
        }
    }
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
}


