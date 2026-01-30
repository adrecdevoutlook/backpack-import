<?php

namespace Adrec\BackpackImport;

use Illuminate\Support\ServiceProvider;

class AdrecImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/backpack-import.php', 'backpack.operations.import');
    }

    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'adrec.backpack-import');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/backpack-import.php' => config_path('backpack/operations/import.php'),
        ], 'backpack-import-config');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/adrec/backpack-import'),
        ], 'backpack-import-views');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'backpack-import-migrations');
    }
}
