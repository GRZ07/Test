<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Services\DynamicTable\ModelMapper;
use App\Services\DynamicTable\TableStructureAnalyzer;
use App\Services\DynamicTable\RelationshipHandler;
use App\Services\DynamicTable\QueryBuilderService;
use App\Services\DynamicTableService;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DynamicTableService::class, function ($app) {
            return new DynamicTableService(
                $app->make(ModelMapper::class),
                $app->make(TableStructureAnalyzer::class),
                $app->make(RelationshipHandler::class),
                $app->make(QueryBuilderService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
