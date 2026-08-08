<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleMiddlewareCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'middleware';

    protected $signature = 'make:module-middleware {name : The middleware name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create middleware inside an existing module';
}
