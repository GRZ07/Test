<?php

namespace App\Filters;

use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class OrWhereFilter implements Filter
{
    protected $field;

    /**
     * Constructor to set the field being filtered.
     *
     * @param string $field
     */
    public function __construct(string $field)
    {
        $this->field = $field;
    }

    /**
     * Apply the filter to the query.
     *
     * @param Builder $query
     * @param mixed $value
     * @param string $property
     */
    public function __invoke(Builder $query, $value, string $property)
    {
        if (is_array($value)) {
            $query->where(function ($query) use ($value) {
                foreach ($value as $filter) {
                    if (isset($filter['type']) && isset($filter['value'])) {
                        switch ($filter['type']) {
                            case 'contains':
                                $query->orWhere($this->field, 'like', '%' . $filter['value'] . '%');
                                break;
                            case 'equals':
                                $query->orWhere($this->field, '=', $filter['value']);
                                break;
                            case 'greaterThan':
                                $query->orWhere($this->field, '>', $filter['value']);
                                break;
                            case 'lessThan':
                                $query->orWhere($this->field, '<', $filter['value']);
                                break;
                            case 'after':
                                $query->orWhereDate($this->field, '>', $filter['value']);
                                break;
                            case 'before':
                                $query->orWhereDate($this->field, '<', $filter['value']);
                                break;
                            case 'between':
                                if (isset($filter['value']['start']) && isset($filter['value']['end'])) {
                                    $start = $filter['value']['start'];
                                    $end = $filter['value']['end'];
                                    $query->orWhereBetween($this->field, [$start, $end]);
                                }
                                break;
                            // Add more cases as needed
                            default:
                                // Handle unknown filter types if necessary
                                break;
                        }
                    }
                }
            });
        }
    }
}
