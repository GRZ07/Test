<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetTableDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'table' => 'required|string',
            'filters' => 'array',
            'filters.*.type' => 'required|string|in:contains,equals,greaterThan,lessThan,after,before,between',
            'filters.*.value' => 'required',
            'sort.column' => 'string',
            'sort.direction' => 'string|in:asc,desc',
            'search' => 'string|nullable',
            'page' => 'integer|min:1',
            'relatedTo' => 'array|nullable',
            'relatedTo.relationship' => 'string|nullable',
            'relatedTo.id' => 'integer|nullable',
            'relatedTo.fromTable' => 'string|nullable',
        ];
    }
}
