<?php

use App\Http\Controllers\DynamicTableController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/users-api', [UserController::class, 'index']);
    Route::get('/users', function () {
        return Inertia::render('UserList');
    });
    //make route for the index post
    Route::get('/posts', [PostController::class,'index']);
    Route::get('/table-names', [DynamicTableController::class, 'getAllTableNames']);
    Route::get('/table-data', [DynamicTableController::class, 'getTableData']);
    Route::get('/tables', function () {
        return Inertia::render(component: 'DynamicTable');
    });

});

require __DIR__ . '/auth.php';
