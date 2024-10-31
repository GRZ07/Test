<?php

namespace App\Services\DynamicTable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ModelMapper
{
    /**
     * Retrieve all model names mapped to their respective table names.
     *
     * @return array
     */
    public function getAllModelTableNames(): array
    {
        $modelMap = [];
        $modelPath = app_path('Models');

        // Get all PHP files in the Models directory and its subdirectories
        $files = File::allFiles($modelPath);

        foreach ($files as $file) {
            // Get the relative path from the Models directory
            $relativePath = $file->getRelativePath();

            // Convert the file path to a class name
            $modelName = $file->getBasename('.php');
            $fullClassName = 'App\\Models\\' . ($relativePath ? str_replace('/', '\\', $relativePath) . '\\' : '') . $modelName;

            // Check if the class exists and is a subclass of Model
            if (class_exists($fullClassName) && is_subclass_of($fullClassName, Model::class)) {
                try {
                    $modelInstance = new $fullClassName;
                    $tableName = $modelInstance->getTable();
                    $modelMap[$fullClassName] = $tableName;
                } catch (\Exception $e) {
                    // Handle potential instantiation errors (e.g., models with required constructor parameters)
                    // You can log the error or skip the model
                    Log::warning("Unable to instantiate model {$fullClassName}: " . $e->getMessage());
                    continue;
                }
            }
        }

        return $modelMap;
    }

    /**
     * Get the corresponding model class for a given table.
     *
     * @param string $table
     * @return string|null
     */
    public function getModelForTable(string $table): ?string
    {
        $modelMap = $this->getModelMap();
        return $modelMap[$table] ?? null;
    }

    /**
     * Map all models to their respective table names.
     *
     * @return array
     */
    public function getModelMap(): array
    {
        $modelMap = [];
        $modelPath = app_path('Models');

        $files = File::allFiles($modelPath);

        foreach ($files as $file) {
             // Get the relative path from the Models directory
             $relativePath = $file->getRelativePath();

             // Convert the file path to a class name
             $modelName = $file->getBasename('.php');
             $fullClassName = 'App\\Models\\' . ($relativePath ? str_replace('/', '\\', $relativePath) . '\\' : '') . $modelName;;

            if (class_exists($fullClassName) && is_subclass_of($fullClassName, Model::class)) {
                try {
                    $modelInstance = new $fullClassName;
                    $tableName = $modelInstance->getTable();
                    $modelMap[$tableName] = $fullClassName;
                } catch (\Exception $e) {
                    Log::warning("Unable to instantiate model {$fullClassName}: " . $e->getMessage());
                    continue;
                }
            }
        }

        return $modelMap;
    }
}
