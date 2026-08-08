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
     * @param  array<string, string>  $replacements
     */
    public function renderStub(string $name, array $replacements = []): string
    {
        $path = dirname(__DIR__, 2)."/stubs/{$name}.stub";

        if (! $this->files->exists($path)) {
            throw new RuntimeException("Stub [{$name}] does not exist.");
        }

        $contents = $this->files->get($path);

        foreach ($replacements as $key => $value) {
            $contents = str_replace('{{ '.$key.' }}', $value, $contents);
        }

        return $contents;
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

    /**
     * @return array<string, string>
     */
    public function genericArtifact(
        string $module,
        string $name,
        string $type,
        ?string $model = null,
        ?string $signature = null,
    ): array {
        $class = class_basename($name);
        $directories = [
            'job' => ['src/Jobs', 'Jobs', 'job'],
            'event' => ['src/Events', 'Events', 'event'],
            'listener' => ['src/Listeners', 'Listeners', 'listener'],
            'notification' => ['src/Notifications', 'Notifications', 'notification'],
            'mail' => ['src/Mail', 'Mail', 'mail'],
            'command' => ['src/Console/Commands', 'Console\\Commands', 'command'],
            'middleware' => ['src/Http/Middleware', 'Http\\Middleware', 'middleware'],
            'rule' => ['src/Rules', 'Rules', 'rule'],
            'observer' => ['src/Observers', 'Observers', 'observer'],
            'cast' => ['src/Casts', 'Casts', 'cast'],
            'enum' => ['src/Enums', 'Enums', 'enum'],
        ];

        if ($type === 'resource') {
            $resourceClass = str_ends_with($class, 'Resource')
                ? substr($class, 0, -strlen('Resource'))
                : $class;
            $modelName = $model ?? $resourceClass;
            $modelName = $this->normalizeName($modelName);
            $modelClass = "Modules\\{$module}\\Models\\{$modelName}";

            return [
                $this->modulePath($module)."/src/Http/Resources/{$class}.php" => $this->renderStub('resource', [
                    'namespace' => "Modules\\{$module}\\Http\\Resources",
                    'model_class' => $modelClass,
                    'model_class_basename' => class_basename($modelName),
                    'class' => $resourceClass,
                ]),
            ];
        }

        if (! isset($directories[$type])) {
            throw new InvalidArgumentException("Unsupported module artifact type [{$type}].");
        }

        [$directory, $namespace, $stub] = $directories[$type];
        $replacements = [
            'namespace' => "Modules\\{$module}\\{$namespace}",
            'class' => $class,
        ];

        if ($type === 'command') {
            $replacements['signature'] = $signature ?? Str::kebab($module).':'.Str::kebab($class);
            $replacements['description'] = "Execute the {$class} command.";
        }

        if ($type === 'observer') {
            $modelName = $this->normalizeName($model ?? 'Model');
            $replacements['model_class'] = "Modules\\{$module}\\Models\\{$modelName}";
            $replacements['model_class_basename'] = class_basename($modelName);
            $replacements['variable'] = Str::camel(class_basename($modelName));
        }

        return [
            $this->modulePath($module)."/{$directory}/".str_replace('\\', '/', $name).'.php' => $this->renderStub($stub, $replacements),
        ];
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
            return $this->renderStub('migration-empty');
        }

        return $this->renderStub('migration', ['table' => $table]);
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

        return $this->renderStub('model', [
            'namespace' => $namespace,
            'imports' => implode(PHP_EOL, $imports),
            'attributes' => $attributes,
            'class' => $class,
            'base_class' => class_basename($baseClass),
            'traits' => $traits,
            'factory_method' => $factoryMethod,
        ]);
    }

    private function factoryContents(string $module, string $class, string $modelClass): string
    {
        return $this->renderStub('factory', [
            'namespace' => "Modules\\{$module}\\Database\\Factories",
            'model_class' => $modelClass,
            'class' => $class,
        ]);
    }

    private function seederContents(string $module, string $class, string $modelClass): string
    {
        return $this->renderStub('seeder', [
            'namespace' => "Modules\\{$module}\\Database\\Seeders",
            'model_class' => $modelClass,
            'class' => $class,
        ]);
    }

    private function policyContents(string $module, string $class, string $modelClass): string
    {
        return $this->renderStub('policy', [
            'namespace' => "Modules\\{$module}\\Policies",
            'model_class' => $modelClass,
            'model_class_basename' => $class,
            'class' => $class,
            'variable' => $this->variableName($class),
        ]);
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

        return $this->renderStub('controller', [
            'namespace' => "Modules\\{$module}\\Http\\Controllers",
            'imports' => implode(PHP_EOL, $imports),
            'class' => $class,
            'methods' => $methods,
        ]);
    }

    private function requestContents(string $module, string $class): string
    {
        return $this->renderStub('request', [
            'namespace' => "Modules\\{$module}\\Http\\Requests",
            'class' => $class,
        ]);
    }

    private function resourceContents(string $module, string $class, string $modelClass): string
    {
        return $this->renderStub('resource', [
            'namespace' => "Modules\\{$module}\\Http\\Resources",
            'model_class' => $modelClass,
            'model_class_basename' => $class,
            'class' => $class,
        ]);
    }

    private function variableName(string $class): string
    {
        return Str::camel($class);
    }
}
