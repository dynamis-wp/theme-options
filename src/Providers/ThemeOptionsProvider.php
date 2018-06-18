<?php namespace Dynamis\ThemeOptions\Providers;

use Dynamis\ThemeOptions\Integration;
use Dynamis\ThemeOptions\Repository;

class ThemeOptionsProvider extends \Dynamis\ServiceProvider
{
    function provides()
    {
        return ['dynamis.theme_options'];
    }

    function register()
    {
        $this->app->singleton('dynamis.theme_options', function ($app) {
            return new Repository();
        });
    }

    function boot()
    {
        // Add admin integration
        $integration = Integration::getInstance();

        $config = app('config')->get('options', []);
        $integration->init($config);

        // Share options to blade
        app('blade')->share('theme', app('dynamis.theme_options'));
    }
}
