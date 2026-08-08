<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Modules\LaravelModules\Console\ModuleArtifactGenerator;
use Throwable;

class MakeModuleControllerCommand extends Command
{
    protected $signature = 'make:module-controller {name : The controller name} {--module= : The existing StudlyCase module name} {--api : Create an API resource controller} {--resource : Create a resource controller} {--requests : Type-hint store and update form requests} {--force : Overwrite generated files}';

    protected $description = 'Create a controller inside an existing module';

    public function handle(ModuleArtifactGenerator $generator): int
    {
        try {
            $module = $generator->normalizeModule((string) $this->option('module'));
            $name = $generator->normalizeName((string) $this->argument('name'));
            $generator->assertModuleExists($module);
            $class = class_basename($name);
            $modelName = str_ends_with($class, 'Controller')
                ? substr($name, 0, -strlen('Controller'))
                : $name;

            $artifacts = $generator->modelArtifacts(
                module: $module,
                name: $modelName,
                controller: true,
                resource: $this->option('api') || $this->option('resource'),
                requests: (bool) $this->option('requests'),
            );
            $controllerArtifacts = array_filter(
                $artifacts,
                fn (string $path): bool => str_contains($path, '/Http/Controllers/') || str_contains($path, '/Http/Requests/'),
                ARRAY_FILTER_USE_KEY,
            );

            $generator->writeArtifacts($controllerArtifacts, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Controller [Modules\\{$module}\\Http\\Controllers\\{$name}] created successfully.");

        return self::SUCCESS;
    }
}
