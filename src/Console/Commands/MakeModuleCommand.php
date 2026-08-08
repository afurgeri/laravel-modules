<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module {name : The StudlyCase module name} {--force : Overwrite generated files that already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a convention-based Laravel module';

    public function handle(): int
    {
        $name = Str::studly((string) $this->argument('name'));

        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            $this->components->error('The module name must contain only letters and numbers and start with a letter.');

            return self::FAILURE;
        }

        $modulePath = base_path("modules/{$name}");
        $providerPath = "{$modulePath}/src/{$name}ServiceProvider.php";
        $routesPath = "{$modulePath}/routes/web.php";

        if (! $this->option('force') && (File::exists($providerPath) || File::exists($routesPath))) {
            $this->components->error("The module [{$name}] already exists. Use --force to overwrite its generated files.");

            return self::FAILURE;
        }

        foreach ([
            "{$modulePath}/src",
            "{$modulePath}/database/factories",
            "{$modulePath}/database/migrations",
            "{$modulePath}/database/seeders",
            "{$modulePath}/routes",
        ] as $directory) {
            File::ensureDirectoryExists($directory);
        }

        File::put($providerPath, $this->providerContents($name));
        File::put($routesPath, $this->routesContents());

        $this->registerAutoload($name);
        $this->registerProvider($name);

        $this->components->info("Module [{$name}] created successfully.");

        return self::SUCCESS;
    }

    private function providerContents(string $name): string
    {
        return str_replace('{{ module }}', $name, <<<'PHP'
        <?php

        namespace Modules\{{ module }};

        use Illuminate\Support\ServiceProvider;

        class {{ module }}ServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            }
        }

        PHP);
    }

    private function routesContents(): string
    {
        return <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
            //
        });

        PHP;
    }

    private function registerAutoload(string $name): void
    {
        $path = base_path('composer.json');

        try {
            /** @var array{autoload?: array{psr-4?: array<string, string>}} $composer */
            $composer = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not parse composer.json: '.$exception->getMessage(), previous: $exception);
        }

        if (! isset($composer['autoload']['psr-4'])) {
            throw new RuntimeException('Could not find the psr-4 autoload section in composer.json.');
        }

        $namespaces = [
            "Modules\\{$name}\\" => "modules/{$name}/src/",
            "Modules\\{$name}\\Database\\Factories\\" => "modules/{$name}/database/factories/",
            "Modules\\{$name}\\Database\\Seeders\\" => "modules/{$name}/database/seeders/",
        ];

        $changed = false;

        foreach ($namespaces as $namespace => $directory) {
            if (isset($composer['autoload']['psr-4'][$namespace])) {
                continue;
            }

            $composer['autoload']['psr-4'][$namespace] = $directory;
            $changed = true;
        }

        if ($changed) {
            File::put($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function registerProvider(string $name): void
    {
        $path = base_path('bootstrap/providers.php');
        $contents = File::get($path);
        $useLine = "use Modules\\{$name}\\{$name}ServiceProvider;";
        $classLine = "    {$name}ServiceProvider::class,";

        if (str_contains($contents, $useLine) && str_contains($contents, trim($classLine))) {
            return;
        }

        $lines = preg_split('/\R/', $contents);

        if ($lines === false) {
            throw new RuntimeException('Could not parse bootstrap/providers.php.');
        }

        $lastUseIndex = null;
        $closingIndex = null;

        foreach ($lines as $index => $line) {
            if (str_starts_with(trim($line), 'use ')) {
                $lastUseIndex = $index;
            }

            if (trim($line) === '];') {
                $closingIndex = $index;
            }
        }

        if ($lastUseIndex === null || $closingIndex === null) {
            throw new RuntimeException('Could not locate the expected structure in bootstrap/providers.php.');
        }

        if (! str_contains($contents, $useLine)) {
            array_splice($lines, $lastUseIndex + 1, 0, [$useLine]);
            $closingIndex++;
        }

        if (! str_contains($contents, trim($classLine))) {
            array_splice($lines, $closingIndex, 0, [$classLine]);
        }

        File::put($path, implode("\n", $lines));
    }
}
