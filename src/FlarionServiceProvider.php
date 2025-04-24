<?php

namespace Doppar\Flarion;

use Phaseolies\Providers\ServiceProvider;
use Doppar\Flarion\ApiAuthenticate;

class FlarionServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('api-auth', ApiAuthenticate::class);

        $this->mergeConfig(
            __DIR__ . '/config/flarion.php',
            'flarion'
        );
    }

    public function boot()
    {
        $this->loadMigrations(__DIR__ . '/database/migrations');

        $this->publishes([
            __DIR__ . '/config/flarion.php' => config_path('flarion.php'),
            __DIR__ . '/database/migrations' => database_path('migrations'),
        ]);
    }
}
