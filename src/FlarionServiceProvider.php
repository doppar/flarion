<?php

namespace Doppar\Flarion;

use Phaseolies\Providers\GhostableProvider;
use Phaseolies\Providers\ServiceProvider;
use Doppar\Flarion\ApiAuthenticate;

class FlarionServiceProvider extends ServiceProvider implements GhostableProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ApiAuthenticate::class, ApiAuthenticate::class);

        $this->mergeConfig(
            __DIR__ . '/config/flarion.php',
            'flarion'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrations(__DIR__ . '/database/migrations');

        $this->publishes([
            __DIR__ . '/database/migrations' => database_path('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/config/flarion.php' => config_path('flarion.php'),
        ], 'config');
    }

    /**
     * Get the services that should ghost-load this provider.
     *
     * @return array<int, string>
     */
    public function ghosts(): array
    {
        return [
            ApiAuthenticate::class,
        ];
    }
}
