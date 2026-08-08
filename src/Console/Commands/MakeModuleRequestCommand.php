<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Modules\LaravelModules\Console\ModuleArtifactGenerator;
use Throwable;

class MakeModuleRequestCommand extends Command
{
    protected $signature = 'make:module-request {name : The request name} {--module= : The existing StudlyCase module name} {--force : Overwrite generated files}';

    protected $description = 'Create a form request inside an existing module';

    public function handle(ModuleArtifactGenerator $generator): int
    {
        try {
            $module = $generator->normalizeModule((string) $this->option('module'));
            $name = $generator->normalizeName((string) $this->argument('name'));
            $generator->assertModuleExists($module);
            $path = $generator->modulePath($module).'/src/Http/Requests/'.str_replace('\\', '/', $name).'.php';
            $contents = "<?php\n\nnamespace Modules\\{$module}\\Http\\Requests;\n\nuse Illuminate\\Foundation\\Http\\FormRequest;\n\nclass ".class_basename($name)." extends FormRequest\n{\n    public function authorize(): bool\n    {\n        return true;\n    }\n\n    public function rules(): array\n    {\n        return [];\n    }\n}\n";
            $generator->writeArtifacts([$path => $contents], (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Request [{$name}] created successfully.");

        return self::SUCCESS;
    }
}
