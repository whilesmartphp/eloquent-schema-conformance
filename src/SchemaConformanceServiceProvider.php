<?php

namespace Whilesmart\SchemaConformance;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Whilesmart\SchemaConformance\Console\Commands\SchemaConform;
use Whilesmart\SchemaConformance\Console\Commands\SchemaVerify;
use Whilesmart\SchemaConformance\Http\Middleware\EnforceSchemaConformance;

class SchemaConformanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/schema.php', 'schema');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/schema.php' => config_path('schema.php'),
        ], 'schema-config');

        if ($this->app->runningInConsole()) {
            $this->commands([SchemaVerify::class, SchemaConform::class]);
        }

        // Expose the middleware under an alias so a host can attach it to a
        // route group without referencing the class.
        $this->app['router']->aliasMiddleware('schema.conformance', EnforceSchemaConformance::class);

        // After every migrate/migrate:fresh, silently conform so the declared
        // spec stays the source of truth and no defensive migrations are needed.
        if (config('schema.auto_conform', true)) {
            Event::listen(MigrationsEnded::class, function () {
                try {
                    $this->app->make(SchemaConformanceService::class)->conform();
                } catch (\Throwable $e) {
                    logger()->warning('Schema auto-conform skipped: '.$e->getMessage());
                }
            });
        }
    }
}
