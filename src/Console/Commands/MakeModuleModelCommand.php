<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Modules\LaravelModules\Console\ModuleArtifactGenerator;
use Throwable;

class MakeModuleModelCommand extends Command
{
    protected $signature = 'make:module-model {name : The model name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files} {--pivot : Create a custom pivot model} {--morph-pivot : Create a custom polymorphic pivot model} {--migration : Create a new migration file for the model} {--factory : Create a model factory} {--seed : Create a database seeder} {--policy : Create a policy} {--controller : Create a controller} {--resource : Create a resource controller and API resource} {--requests : Create store and update form requests} {--all : Create the model and its common supporting artifacts}';

    protected $description = 'Create an Eloquent model and optional supporting artifacts inside an existing module';

    public function handle(ModuleArtifactGenerator $generator): int
    {
        try {
            $module = $generator->normalizeModule((string) $this->option('module'));
            $name = $generator->normalizeName((string) $this->argument('name'));
            $generator->assertModuleExists($module);

            if ($this->option('pivot') && $this->option('morph-pivot')) {
                throw new \InvalidArgumentException('The --pivot and --morph-pivot options cannot be used together.');
            }

            $all = (bool) $this->option('all');
            $resource = $all || (bool) $this->option('resource');
            $artifacts = $generator->modelArtifacts(
                module: $module,
                name: $name,
                factory: $all || (bool) $this->option('factory'),
                seed: $all || (bool) $this->option('seed'),
                policy: $all || (bool) $this->option('policy'),
                controller: $all || (bool) $this->option('controller') || $resource,
                resource: $resource,
                requests: $all || (bool) $this->option('requests'),
                migration: $all || (bool) $this->option('migration'),
                pivot: (bool) $this->option('pivot'),
                morphPivot: (bool) $this->option('morph-pivot'),
            );

            $generator->writeArtifacts($artifacts, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Model [Modules\\{$module}\\Models\\{$name}] created successfully.");

        return self::SUCCESS;
    }
}
