<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleEnumCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'enum';

    protected $signature = 'make:module-enum {name : The enum name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create an enum inside an existing module';
}
