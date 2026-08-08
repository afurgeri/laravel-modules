<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->module = 'Catalog';
    $this->modulePath = base_path("modules/{$this->module}");
    $this->composerPath = base_path('composer.json');
    $this->providersPath = base_path('bootstrap/providers.php');
    $this->composerContents = File::get($this->composerPath);
    $this->providersContents = File::get($this->providersPath);

    File::put($this->providersPath, <<<'PHP'
    <?php

    use App\Providers\AppServiceProvider;

    return [
        AppServiceProvider::class,
    ];
    PHP);
});

afterEach(function (): void {
    File::deleteDirectory($this->modulePath);
    File::put($this->composerPath, $this->composerContents);
    File::put($this->providersPath, $this->providersContents);
});

test('make:module creates the standard module layout and registers it', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    expect(File::exists("{$this->modulePath}/src/CatalogServiceProvider.php"))->toBeTrue()
        ->and(File::exists("{$this->modulePath}/routes/web.php"))->toBeTrue()
        ->and(File::isDirectory("{$this->modulePath}/database/factories"))->toBeTrue()
        ->and(File::isDirectory("{$this->modulePath}/database/migrations"))->toBeTrue()
        ->and(File::isDirectory("{$this->modulePath}/database/seeders"))->toBeTrue()
        ->and(File::get("{$this->modulePath}/src/CatalogServiceProvider.php"))->toContain('namespace Modules\\Catalog;')
        ->and(File::get("{$this->modulePath}/routes/web.php"))->toContain("Route::middleware(['web', 'auth', 'verified'])");

    $composer = json_decode(File::get($this->composerPath), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['autoload']['psr-4'])
        ->toHaveKey('Modules\\Catalog\\', 'modules/Catalog/src/')
        ->toHaveKey('Modules\\Catalog\\Database\\Factories\\', 'modules/Catalog/database/factories/')
        ->toHaveKey('Modules\\Catalog\\Database\\Seeders\\', 'modules/Catalog/database/seeders/')
        ->and(File::get($this->providersPath))
        ->toContain('use Modules\\Catalog\\CatalogServiceProvider;')
        ->toContain('CatalogServiceProvider::class,');
});

test('make:module does not overwrite an existing module without force', function (): void {
    File::ensureDirectoryExists($this->modulePath.'/src');
    File::put($this->modulePath.'/src/CatalogServiceProvider.php', '<?php // existing');

    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(1);

    expect(File::get($this->modulePath.'/src/CatalogServiceProvider.php'))->toBe('<?php // existing');
});

test('make:module-model creates a model inside an existing module', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    $this->artisan('make:module-model', [
        'name' => 'Product',
        '--module' => $this->module,
    ])->assertExitCode(0);

    expect(File::get($this->modulePath.'/src/Models/Product.php'))
        ->toContain('namespace Modules\\Catalog\\Models;')
        ->toContain('class Product extends Model');
});

test('make:module-model --migration creates the migration inside the module', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    $this->artisan('make:module-model', [
        'name' => 'Product',
        '--module' => $this->module,
        '--migration' => true,
    ])->assertExitCode(0);

    $migrations = File::glob("{$this->modulePath}/database/migrations/*_create_products_table.php");

    expect($migrations)->not->toBeEmpty()
        ->and(File::get($migrations[0]))
        ->toContain("Schema::create('products'")
        ->toContain("Schema::dropIfExists('products')");
});
