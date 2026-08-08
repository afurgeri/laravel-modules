<?php

namespace Modules\LaravelModules;

use Illuminate\Support\ServiceProvider;
use Modules\LaravelModules\Console\Commands\MakeModuleCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleControllerCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleFactoryCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleMigrationCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleModelCommand;
use Modules\LaravelModules\Console\Commands\MakeModulePolicyCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleRequestCommand;

class LaravelModulesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                MakeModuleModelCommand::class,
                MakeModuleControllerCommand::class,
                MakeModuleFactoryCommand::class,
                MakeModuleMigrationCommand::class,
                MakeModulePolicyCommand::class,
                MakeModuleRequestCommand::class,
            ]);
        }
    }
}
