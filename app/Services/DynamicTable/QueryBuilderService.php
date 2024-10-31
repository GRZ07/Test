<?php

namespace App\Services\DynamicTable;

use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueryBuilderService
{
    /**
     * Build the initial query with allowed sorts and eager loading.
     *
     * @param string $modelClass
     * @param array $columns
     * @param array $relationships
     * @return QueryBuilder
     */
    public function buildQuery(string $modelClass, array $columns, array $relationships): QueryBuilder
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
     * @param \Spatie\QueryBuilder\QueryBuilder $query
     * @param Request $request
     * @param array $columns
     * @param array $columnTypes
     * @return void
     */
    public function applySearchFilter(QueryBuilder $query, Request $request, array $columns, array $columnTypes): void
    {
        if ($search = $request->input('search')) {
            $searchableColumns = array_filter($columns, function ($column) use ($columnTypes) {
                return in_array($columnTypes[$column], ['string', 'date']);
            });

            if (!empty($searchableColumns)) {
                $query->where(function ($q) use ($search, $searchableColumns) {
                    foreach ($searchableColumns as $column) {
                        $q->orWhere($column, 'like', '%' . $search . '%');
                    }
                });
            }
        }
    }

    /**
     * Apply custom filters to the query.
     *
     * @param \Spatie\QueryBuilder\QueryBuilder $query
     * @param Request $request
     * @param array $columnTypes
     * @param array $relationshipDetails
     * @return void
     */
    public function applyCustomFilters(QueryBuilder $query, Request $request, array $columnTypes, array $relationshipDetails): void
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
     * @param \Spatie\QueryBuilder\QueryBuilder $query
     * @param string $key
     * @param array $filter
     * @param string $columnType
     * @param string|null $relationshipType
     * @return void
     */
    protected function applyFilters(QueryBuilder $query, string $key, array $filter, string $columnType, ?string $relationshipType): void
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
     * @param \Spatie\QueryBuilder\QueryBuilder $query
     * @param string $key
     * @param string $filterType
     * @param mixed $filterValue
     * @param string $columnType
     * @return void
     */
    protected function applyCountFilter(QueryBuilder $query, string $key, string $filterType, $filterValue, string $columnType): void
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
     * @param \Spatie\QueryBuilder\QueryBuilder $query
     * @param string $key
     * @param string $filterType
     * @param mixed $filterValue
     * @param string $columnType
     * @return void
     */
    protected function applyRegularFilter(QueryBuilder $query, string $key, string $filterType, $filterValue, string $columnType): void
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
     * @param \Spatie\QueryBuilder\QueryBuilder $query
     * @param string $key
     * @param mixed $filterValue
     * @param string $columnType
     * @param bool $isHaving
     * @return void
     */
    protected function applyBetweenFilter(QueryBuilder $query, string $key, $filterValue, string $columnType, bool $isHaving): void
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
     * @param \Spatie\QueryBuilder\QueryBuilder $query
     * @param Request $request
     * @param string $modelClass
     * @return void
     */
    public function applyRelatedToFilter(QueryBuilder $query, Request $request, string $modelClass): void
    {
        if ($request->has('relatedTo')) {
            $relatedTo = $request->input('relatedTo');
            $relationshipName = $relatedTo['relationship'] ?? null;
            $relatedId = $relatedTo['id'] ?? null;
            $fromTable = $relatedTo['fromTable'] ?? null;

            if ($relationshipName && $relatedId && $fromTable) {
                // Assume that you have access to ModelMapper or inject it
                // For simplicity, let's instantiate ModelMapper here
                $modelMapper = new ModelMapper();
                $fromModelClass = $modelMapper->getModelForTable($fromTable);

                if ($fromModelClass) {
                    // Assume you have RelationshipHandler
                    $relationshipHandler = new RelationshipHandler();
                    $inverseRelationshipName = $relationshipHandler->getInverseRelationshipName($fromModelClass);

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
     * Validate date format.
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    protected function isValidDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
