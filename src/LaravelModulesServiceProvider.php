<?php

namespace Modules\LaravelModules;

use Illuminate\Support\ServiceProvider;
use Modules\LaravelModules\Console\Commands\MakeModuleCastCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleCommandArtifactCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleControllerCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleEnumCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleEventCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleFactoryCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleJobCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleListenerCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleMailCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleMiddlewareCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleMigrationCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleModelCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleNotificationCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleObserverCommand;
use Modules\LaravelModules\Console\Commands\MakeModulePolicyCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleRequestCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleResourceCommand;
use Modules\LaravelModules\Console\Commands\MakeModuleRuleCommand;

class LaravelModulesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                MakeModuleCommandArtifactCommand::class,
                MakeModuleModelCommand::class,
                MakeModuleControllerCommand::class,
                MakeModuleCastCommand::class,
                MakeModuleEnumCommand::class,
                MakeModuleEventCommand::class,
                MakeModuleFactoryCommand::class,
                MakeModuleJobCommand::class,
                MakeModuleListenerCommand::class,
                MakeModuleMailCommand::class,
                MakeModuleMiddlewareCommand::class,
                MakeModuleMigrationCommand::class,
                MakeModuleNotificationCommand::class,
                MakeModuleObserverCommand::class,
                MakeModulePolicyCommand::class,
                MakeModuleResourceCommand::class,
                MakeModuleRequestCommand::class,
                MakeModuleRuleCommand::class,
            ]);
        }
    }
}
