<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleEventCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'event';

    protected $signature = 'make:module-event {name : The event name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create an event inside an existing module';
}
