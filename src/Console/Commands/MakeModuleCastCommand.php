<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleCastCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'cast';

    protected $signature = 'make:module-cast {name : The cast name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create an Eloquent cast inside an existing module';
}
