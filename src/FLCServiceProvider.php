<?php
namespace Leazycms\FLC;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
class FLCServiceProvider extends ServiceProvider
{
    protected function registerRoutes()
    {
        Route::middleware(['web'])
        ->group(function () {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        });

    }


    protected function registerMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . "/database/migrations");
    }


    public function boot(Router $router)
    {
        $this->registerMigrations();
        $this->loadViewsFrom(__DIR__ . '/views', 'flc');
        $this->registerRoutes();

    }
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . "/config/config.php", "flc");
        $this->registerFunctions();
    }
    protected function registerFunctions()
    {
        require_once(__DIR__ . "/Inc/helpers.php");
    }

}
