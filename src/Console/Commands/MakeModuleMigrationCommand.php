<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\LaravelModules\Console\ModuleArtifactGenerator;
use Throwable;

class MakeModuleMigrationCommand extends Command
{
    protected $signature = 'make:module-migration {name : The migration name} {--module= : The existing StudlyCase module name} {--create= : The table to create}';

    protected $description = 'Create a migration inside an existing module';

    public function handle(ModuleArtifactGenerator $generator): int
    {
        try {
            $module = $generator->normalizeModule((string) $this->option('module'));
            $name = Str::snake((string) $this->argument('name'));
            $generator->assertModuleExists($module);
            $table = $this->option('create') !== null
                ? Str::snake((string) $this->option('create'))
                : null;
            $path = $generator->migrationPath($module, $table ?? 'table');
            $contents = $generator->migrationContents($table);
            $path = str_replace('_create_'.($table ?? 'table').'_table.php', "_{$name}.php", $path);
            $generator->writeArtifacts([$path => $contents]);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Migration [{$path}] created successfully.");

        return self::SUCCESS;
    }
}
