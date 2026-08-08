<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleNotificationCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'notification';

    protected $signature = 'make:module-notification {name : The notification name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create a notification inside an existing module';
}
