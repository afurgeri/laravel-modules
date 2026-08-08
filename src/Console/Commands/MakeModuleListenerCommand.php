<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleListenerCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'listener';

    protected $signature = 'make:module-listener {name : The listener name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create an event listener inside an existing module';
}
