<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module-model {name : The model name} {--module= : The existing StudlyCase module name} {--force : Overwrite the model if it exists} {--pivot : Create a custom pivot model} {--morph-pivot : Create a custom polymorphic pivot model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an Eloquent model inside an existing module';

    public function handle(): int
    {
        $module = Str::studly((string) $this->option('module'));
        $name = Str::studly(str_replace('/', '\\', (string) $this->argument('name')));

        if ($module === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $module)) {
            $this->components->error('The --module option must name an existing module.');

            return self::FAILURE;
        }

        if (! File::isDirectory(base_path("modules/{$module}"))) {
            $this->components->error("The module [{$module}] does not exist. Run [make:module {$module}] first.");

            return self::FAILURE;
        }

        $path = base_path("modules/{$module}/src/Models/".str_replace('\\', '/', $name).'.php');

        if (File::exists($path) && ! $this->option('force')) {
            $this->components->error('Model already exists.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->modelContents($module, $name));

        $this->components->info("Model [Modules\\{$module}\\Models\\{$name}] created successfully.");

        return self::SUCCESS;
    }

    private function modelContents(string $module, string $name): string
    {
        $namespace = "Modules\\{$module}\\Models";
        $class = class_basename($name);
        $subnamespace = str_contains($name, '\\') ? '\\'.dirname(str_replace('\\', '/', $name)) : '';
        $baseClass = match (true) {
            (bool) $this->option('morph-pivot') => 'Illuminate\\Database\\Eloquent\\Relations\\MorphPivot',
            (bool) $this->option('pivot') => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
            default => 'Illuminate\\Database\\Eloquent\\Model',
        };

        return <<<PHP
        <?php

        namespace {$namespace}{$subnamespace};

        use {$baseClass};

        class {$class} extends {$this->baseClassName($baseClass)}
        {
            //
        }

        PHP;
    }

    private function baseClassName(string $baseClass): string
    {
        return class_basename($baseClass);
    }
}
