<?php

namespace Modules\LaravelModules\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ModuleArtifactGenerator
{
    public function __construct(private readonly Filesystem $files) {}

    public function modulePath(string $module): string
    {
        return base_path("modules/{$module}");
    }

    public function normalizeModule(string $module): string
    {
        $normalized = Str::studly($module);

        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $normalized)) {
            throw new InvalidArgumentException('The module name must contain only letters and numbers and start with a letter.');
        }

        return $normalized;
    }

    public function normalizeName(string $name): string
    {
        $normalized = Str::studly(str_replace('/', '\\', $name));

        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*(\\\\[A-Za-z][A-Za-z0-9]*)*$/', $normalized)) {
            throw new InvalidArgumentException('The name must contain valid PHP class segments separated by slashes.');
        }

        return $normalized;
    }

    public function assertModuleExists(string $module): void
    {
        if (! $this->files->isDirectory($this->modulePath($module))) {
            throw new RuntimeException("The module [{$module}] does not exist. Run [make:module {$module}] first.");
        }
    }

    /**
     * @param  array<string, string>  $artifacts
     * @return list<string>
     */
    public function writeArtifacts(array $artifacts, bool $force = false): array
    {
        $conflicts = array_keys(array_filter(
            $artifacts,
            fn (string $contents, string $path): bool => $this->files->exists($path),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($conflicts !== [] && ! $force) {
            throw new RuntimeException('The following files already exist: '.implode(', ', $conflicts).'. Use --force to overwrite them.');
        }

        foreach ($artifacts as $path => $contents) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $contents);
        }

        return array_keys($artifacts);
    }

    /**
     * @return array<string, string>
     */
    public function modelArtifacts(
        string $module,
        string $name,
        bool $factory = false,
        bool $seed = false,
        bool $policy = false,
        bool $controller = false,
        bool $resource = false,
        bool $requests = false,
        bool $migration = false,
        bool $pivot = false,
        bool $morphPivot = false,
    ): array {
        $artifacts = [];
        $class = class_basename($name);
        $subnamespace = str_contains($name, '\\') ? '\\'.trim(dirname(str_replace('\\', '/', $name)), '.') : '';
        $modelNamespace = "Modules\\{$module}\\Models{$subnamespace}";
        $modelClass = "{$modelNamespace}\\{$class}";
        $table = Str::snake(Str::pluralStudly($class));

        if ($pivot) {
            $table = Str::singular($table);
        }

        $artifacts[$this->modulePath($module).'/src/Models/'.str_replace('\\', '/', $name).'.php'] = $this->modelContents(
            module: $module,
            name: $name,
            factory: $factory,
            policy: $policy,
            pivot: $pivot,
            morphPivot: $morphPivot,
        );

        if ($factory) {
            $factoryClass = "{$class}Factory";
            $artifacts[$this->modulePath($module)."/database/factories/{$factoryClass}.php"] = $this->factoryContents($module, $class, $modelClass);
        }

        if ($seed) {
            $artifacts[$this->modulePath($module)."/database/seeders/{$class}Seeder.php"] = $this->seederContents($module, $class, $modelClass);
        }

        if ($policy) {
            $artifacts[$this->modulePath($module)."/src/Policies/{$class}Policy.php"] = $this->policyContents($module, $class, $modelClass);
        }

        if ($controller || $resource) {
            $artifacts[$this->modulePath($module)."/src/Http/Controllers/{$class}Controller.php"] = $this->controllerContents(
                module: $module,
                class: $class,
                modelClass: $modelClass,
                resource: $resource,
                requests: $requests,
            );
        }

        if ($resource) {
            $artifacts[$this->modulePath($module)."/src/Http/Resources/{$class}Resource.php"] = $this->resourceContents($module, $class, $modelClass);
        }

        if ($requests) {
            $artifacts[$this->modulePath($module)."/src/Http/Requests/Store{$class}Request.php"] = $this->requestContents($module, "Store{$class}Request");
            $artifacts[$this->modulePath($module)."/src/Http/Requests/Update{$class}Request.php"] = $this->requestContents($module, "Update{$class}Request");
        }

        if ($migration) {
            $artifacts[$this->migrationPath($module, $table)] = $this->migrationContents($table);
        }

        return $artifacts;
    }

    public function migrationPath(string $module, string $table): string
    {
        $directory = $this->modulePath($module).'/database/migrations';
        $timestamp = time();
        $attempt = 0;

        do {
            $path = sprintf('%s/%s_create_%s_table.php', $directory, date('Y_m_d_His', $timestamp + $attempt), $table);
            $attempt++;
        } while ($this->files->exists($path));

        return $path;
    }

    public function migrationContents(?string $table = null): string
    {
        if ($table === null) {
            return <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;

            return new class extends Migration
            {
                public function up(): void
                {
                    //
                }

                public function down(): void
                {
                    //
                }
            };
            PHP;
        }

        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table): void {
                    \$table->id();
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP;
    }

    private function modelContents(string $module, string $name, bool $factory, bool $policy, bool $pivot, bool $morphPivot): string
    {
        $class = class_basename($name);
        $subnamespace = str_contains($name, '\\') ? '\\'.trim(dirname(str_replace('\\', '/', $name)), '.') : '';
        $namespace = "Modules\\{$module}\\Models{$subnamespace}";
        $baseClass = $morphPivot ? 'Illuminate\\Database\\Eloquent\\Relations\\MorphPivot' : ($pivot ? 'Illuminate\\Database\\Eloquent\\Relations\\Pivot' : 'Illuminate\\Database\\Eloquent\\Model');
        $imports = ["use {$baseClass};"];
        $traits = '';
        $attributes = '';
        $factoryMethod = '';

        if ($factory && ! $pivot && ! $morphPivot) {
            $imports[] = 'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;';
            $imports[] = "use Modules\\{$module}\\Database\\Factories\\{$class}Factory;";
            $traits = '    use HasFactory;'.PHP_EOL;
            $factoryMethod = PHP_EOL."    protected static function newFactory(): {$class}Factory".PHP_EOL.'    {'.PHP_EOL."        return {$class}Factory::new();".PHP_EOL.'    }'.PHP_EOL;
        }

        if ($policy && ! $pivot && ! $morphPivot) {
            $imports[] = 'use Illuminate\\Database\\Eloquent\\Attributes\\UsePolicy;';
            $imports[] = "use Modules\\{$module}\\Policies\\{$class}Policy;";
            $attributes = "#[UsePolicy({$class}Policy::class)]".PHP_EOL;
        }

        return "<?php\n\nnamespace {$namespace};\n\n".implode(PHP_EOL, $imports)."\n\n{$attributes}class {$class} extends ".class_basename($baseClass)."\n{\n{$traits}{$factoryMethod}}\n";
    }

    private function factoryContents(string $module, string $class, string $modelClass): string
    {
        return "<?php\n\nnamespace Modules\\{$module}\\Database\\Factories;\n\nuse {$modelClass};\nuse Illuminate\\Database\\Eloquent\\Factories\\Factory;\n\n/** @extends Factory<{$class}> */\nclass {$class}Factory extends Factory\n{\n    protected \$model = {$class}::class;\n\n    public function definition(): array\n    {\n        return [];\n    }\n}\n";
    }

    private function seederContents(string $module, string $class, string $modelClass): string
    {
        return "<?php\n\nnamespace Modules\\{$module}\\Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\nuse {$modelClass};\n\nclass {$class}Seeder extends Seeder\n{\n    public function run(): void\n    {\n        // {$class}::factory()->count(10)->create();\n    }\n}\n";
    }

    private function policyContents(string $module, string $class, string $modelClass): string
    {
        return "<?php\n\nnamespace Modules\\{$module}\\Policies;\n\nuse {$modelClass};\n\nclass {$class}Policy\n{\n    public function viewAny(mixed \$user): bool\n    {\n        return true;\n    }\n\n    public function view(mixed \$user, {$class} \${$this->variableName($class)}): bool\n    {\n        return true;\n    }\n}\n";
    }

    private function controllerContents(string $module, string $class, string $modelClass, bool $resource, bool $requests): string
    {
        $imports = ["use {$modelClass};", 'use Illuminate\\Http\\Response;'];
        $methods = '';

        if ($requests) {
            $imports[] = "use Modules\\{$module}\\Http\\Requests\\Store{$class}Request;";
            $imports[] = "use Modules\\{$module}\\Http\\Requests\\Update{$class}Request;";
        }

        if ($resource) {
            $methods = "    public function index(): Response\n    {\n        return response()->noContent();\n    }\n\n    public function store(".($requests ? "Store{$class}Request \$request" : '')."): Response\n    {\n        return response()->noContent();\n    }\n\n    public function show({$class} \${$this->variableName($class)}): Response\n    {\n        return response()->noContent();\n    }\n\n    public function update(".($requests ? "Update{$class}Request \$request, " : '')."{$class} \${$this->variableName($class)}): Response\n    {\n        return response()->noContent();\n    }\n\n    public function destroy({$class} \${$this->variableName($class)}): Response\n    {\n        return response()->noContent();\n    }\n";
        }

        return "<?php\n\nnamespace Modules\\{$module}\\Http\\Controllers;\n\n".implode(PHP_EOL, $imports)."\n\nclass {$class}Controller\n{\n{$methods}}\n";
    }

    private function requestContents(string $module, string $class): string
    {
        return "<?php\n\nnamespace Modules\\{$module}\\Http\\Requests;\n\nuse Illuminate\\Foundation\\Http\\FormRequest;\n\nclass {$class} extends FormRequest\n{\n    public function authorize(): bool\n    {\n        return true;\n    }\n\n    public function rules(): array\n    {\n        return [];\n    }\n}\n";
    }

    private function resourceContents(string $module, string $class, string $modelClass): string
    {
        return "<?php\n\nnamespace Modules\\{$module}\\Http\\Resources;\n\nuse {$modelClass};\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Http\\Resources\\Json\\JsonResource;\n\n/** @mixin {$class} */\nclass {$class}Resource extends JsonResource\n{\n    public function toArray(Request \$request): array\n    {\n        return parent::toArray(\$request);\n    }\n}\n";
    }

    private function variableName(string $class): string
    {
        return Str::camel($class);
    }
}
