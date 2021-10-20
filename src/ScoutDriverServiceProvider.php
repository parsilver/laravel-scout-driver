<?php

namespace Farzai\ScoutDriver;

use Elastic\EnterpriseSearch\Client;
use Farzai\ScoutDriver\Drivers;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ScoutDriverServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->mergeConfigFrom(__DIR__."/../config.php", "scout");
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
            ]);
        }

        $this->extendDrivers();
    }


    private function extendDrivers()
    {
        resolve(EngineManager::class)->extend('app-search', function ($app) {
            $config = $app['config']->get('scout.app-search');

            $client = new Client([
                'host' => $config['endpoint'],
                'app-search' => [
                    'token' => $config['key'],
                ]
            ]);

            return new Drivers\AppSearchDriver($client, $config);
        });
    }
}