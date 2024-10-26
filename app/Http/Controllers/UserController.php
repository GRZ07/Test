<?php

namespace App\Http\Controllers;

use App\Models\User;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return QueryBuilder::for(User::class)
            ->allowedFilters(['name', 'email']) // Allow filtering by these fields
            ->allowedSorts(['name', 'email']) // Allow sorting by these fields
            ->paginate($request->input('per_page', 10)); // Pagination
    }
}

