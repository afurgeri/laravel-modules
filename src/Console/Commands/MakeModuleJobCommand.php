<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleJobCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'job';

    protected $signature = 'make:module-job {name : The job name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create a queued job inside an existing module';
}
