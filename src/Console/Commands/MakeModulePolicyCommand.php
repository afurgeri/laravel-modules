<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Modules\LaravelModules\Console\ModuleArtifactGenerator;
use Throwable;

class MakeModulePolicyCommand extends Command
{
    protected $signature = 'make:module-policy {name : The policy name} {--module= : The existing StudlyCase module name} {--model= : The model class name} {--force : Overwrite generated files}';

    protected $description = 'Create a policy inside an existing module';

    public function handle(ModuleArtifactGenerator $generator): int
    {
        try {
            $module = $generator->normalizeModule((string) $this->option('module'));
            $policy = $generator->normalizeName((string) $this->argument('name'));
            $model = $generator->normalizeName((string) ($this->option('model') ?: str_replace('Policy', '', $policy)));
            $generator->assertModuleExists($module);
            $class = class_basename($model);
            $modelClass = "Modules\\{$module}\\Models\\{$model}";
            $path = $generator->modulePath($module).'/src/Policies/'.str_replace('\\', '/', $policy).'.php';
            $contents = "<?php\n\nnamespace Modules\\{$module}\\Policies;\n\nuse {$modelClass};\n\nclass ".class_basename($policy)."\n{\n    public function viewAny(mixed \$user): bool\n    {\n        return true;\n    }\n\n    public function view(mixed \$user, {$class} \$model): bool\n    {\n        return true;\n    }\n}\n";
            $generator->writeArtifacts([$path => $contents], (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Policy [{$policy}] created successfully.");

        return self::SUCCESS;
    }
}
