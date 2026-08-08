<?php

namespace Modules\LaravelModules\Console\Commands;

use Illuminate\Console\Command;
use Modules\LaravelModules\Console\ModuleArtifactGenerator;
use Throwable;

abstract class MakeModuleGenericCommand extends Command
{
    protected string $artifactType;

    public function handle(ModuleArtifactGenerator $generator): int
    {
        try {
            $module = $generator->normalizeModule((string) $this->option('module'));
            $name = $generator->normalizeName((string) $this->argument('name'));
            $generator->assertModuleExists($module);
            $model = $this->getDefinition()->hasOption('model')
                ? $this->option('model')
                : null;
            $signature = $this->getDefinition()->hasOption('signature')
                ? $this->option('signature')
                : null;
            $artifacts = $generator->genericArtifact(
                module: $module,
                name: $name,
                type: $this->artifactType,
                model: $model !== null
                    ? (string) $model
                    : null,
                signature: $signature !== null
                    ? (string) $signature
                    : null,
            );
            $generator->writeArtifacts($artifacts, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$this->artifactType} [{$name}] created successfully.");

        return self::SUCCESS;
    }
}
