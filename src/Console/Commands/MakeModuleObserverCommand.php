<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleObserverCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'observer';

    protected $signature = 'make:module-observer {name : The observer name} {--module= : The existing StudlyCase module name} {--model= : The observed model name} {--force : Overwrite generated files}';

    protected $description = 'Create an Eloquent observer inside an existing module';
}
