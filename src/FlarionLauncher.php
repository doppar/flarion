<?php

namespace Doppar\Flarion;

use Phaseolies\Launchers\GhostableLauncher;
use Phaseolies\Launchers\ServiceLauncher;
use Doppar\Flarion\ApiAuthenticate;

class FlarionLauncher extends ServiceLauncher implements GhostableLauncher
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
    public function launch()
    {
        $this->loadMigrations(__DIR__ . '/database/migrations');

        $this->publishes([
            __DIR__ . '/database/migrations' => schema_path('migrations'),
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
