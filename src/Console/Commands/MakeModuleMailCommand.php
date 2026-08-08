<?php

namespace Modules\LaravelModules\Console\Commands;

class MakeModuleMailCommand extends MakeModuleGenericCommand
{
    protected string $artifactType = 'mail';

    protected $signature = 'make:module-mail {name : The mailable name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create a mailable inside an existing module';
}
