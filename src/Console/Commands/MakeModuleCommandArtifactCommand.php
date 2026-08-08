<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleCommandArtifactCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'command';

    protected $signature = 'make:module-command {name : The command class name} {--module= : The existing StudlyCase module name} {--signature= : The Artisan command signature} {--force : Overwrite generated files}';

    protected $description = 'Create an Artisan command inside an existing module';
}
