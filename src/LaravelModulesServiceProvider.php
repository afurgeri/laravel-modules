<?php

namespace Modules\LaravelModules;

use Illuminate\Support\ServiceProvider;
use Modules\LaravelModules\Console\Commands\MakeModuleCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleModelCommand;

class LaravelModulesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                MakeModuleModelCommand::class,
            ]);
        }
    }
}
