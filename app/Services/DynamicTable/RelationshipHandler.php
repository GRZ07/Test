<?php

namespace App\Services\DynamicTable;

use ReflectionClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RelationshipHandler
{
    /**
     * Retrieve all relationship methods of a model.
     *
     * @param string $modelClass
     * @return array
     */
    public function getModelRelations(string $modelClass): array
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
    public function getRelationshipType(string $modelClass, string $relationship): string
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

    /**
     * Get relationship details and update columns and column types.
     *
     * @param string $modelClass
     * @param array $relationships
     * @param array &$columns
     * @param array &$columnTypes
     * @return array
     */
    public function getRelationshipDetails(string $modelClass, array $relationships, array &$columns, array &$columnTypes): array
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
     * Get the inverse relationship name between two models.
     *
     * @param string $fromModelClass
     * @return string
     */
    public function getInverseRelationshipName(string $fromModelClass): string
    {
        $modelClassName = class_basename($fromModelClass);
        return Str::camel(Str::plural($modelClassName));
    }
}
