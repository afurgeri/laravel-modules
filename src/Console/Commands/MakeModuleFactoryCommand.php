<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Modules\LaravelModules\Console\ModuleArtifactGenerator;
use Throwable;

class MakeModuleFactoryCommand extends Command
{
    protected $signature = 'make:module-factory {name : The factory name} {--module= : The existing StudlyCase module name} {--model= : The model class name} {--force : Overwrite generated files}';

    protected $description = 'Create a model factory inside an existing module';

    public function handle(ModuleArtifactGenerator $generator): int
    {
        try {
            $module = $generator->normalizeModule((string) $this->option('module'));
            $factory = $generator->normalizeName((string) $this->argument('name'));
            $model = $generator->normalizeName((string) ($this->option('model') ?: str_replace('Factory', '', $factory)));
            $generator->assertModuleExists($module);
            $class = class_basename($model);
            $modelNamespace = "Modules\\{$module}\\Models\\{$model}";
            $path = $generator->modulePath($module).'/database/factories/'.str_replace('\\', '/', $factory).'.php';
            $contents = "<?php\n\nnamespace Modules\\{$module}\\Database\\Factories;\n\nuse {$modelNamespace};\nuse Illuminate\\Database\\Eloquent\\Factories\\Factory;\n\n/** @extends Factory<{$class}> */\nclass ".class_basename($factory)." extends Factory\n{\n    protected \$model = {$class}::class;\n\n    public function definition(): array\n    {\n        return [];\n    }\n}\n";
            $generator->writeArtifacts([$path => $contents], (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Factory [{$factory}] created successfully.");

        return self::SUCCESS;
    }
}
