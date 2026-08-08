<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleRuleCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'rule';

    protected $signature = 'make:module-rule {name : The rule name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create a validation rule inside an existing module';
}
