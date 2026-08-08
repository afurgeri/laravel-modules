<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleResourceCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'resource';

    protected $signature = 'make:module-resource {name : The resource name} {--module= : The existing StudlyCase module name} {--model= : The model class name} {--force : Overwrite generated files}';

    protected $description = 'Create an API resource inside an existing module';
}
