<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

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
        ->toContain('use Illuminate\\Database\\Migrations\\Migration;')
        ->toContain("Schema::create('products'")
        ->toContain("Schema::dropIfExists('products')");
});

test('make:module-model --all creates the common model artifacts', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    $this->artisan('make:module-model', [
        'name' => 'Product',
        '--module' => $this->module,
        '--all' => true,
    ])->assertExitCode(0);

    expect(File::exists($this->modulePath.'/src/Models/Product.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/database/factories/ProductFactory.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/database/seeders/ProductSeeder.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Policies/ProductPolicy.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Http/Controllers/ProductController.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Http/Resources/ProductResource.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Http/Requests/StoreProductRequest.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Http/Requests/UpdateProductRequest.php'))->toBeTrue()
        ->and(File::glob($this->modulePath.'/database/migrations/*_create_products_table.php'))->not->toBeEmpty()
        ->and(File::get($this->modulePath.'/src/Models/Product.php'))
        ->toContain('use HasFactory;')
        ->toContain('#[UsePolicy(ProductPolicy::class)]');
});

test('module generators do not partially write artifacts after a conflict', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    File::ensureDirectoryExists($this->modulePath.'/src/Models');
    File::put($this->modulePath.'/src/Models/Product.php', '<?php // existing');

    $this->artisan('make:module-model', [
        'name' => 'Product',
        '--module' => $this->module,
        '--all' => true,
    ])->assertExitCode(1);

    expect(File::get($this->modulePath.'/src/Models/Product.php'))->toBe('<?php // existing')
        ->and(File::exists($this->modulePath.'/database/factories/ProductFactory.php'))->toBeFalse();
});

test('module generators create standalone artifacts', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    $this->artisan('make:module-controller', [
        'name' => 'ReportController',
        '--module' => $this->module,
        '--api' => true,
    ])->assertExitCode(0);

    $this->artisan('make:module-request', [
        'name' => 'StoreReportRequest',
        '--module' => $this->module,
    ])->assertExitCode(0);

    $this->artisan('make:module-factory', [
        'name' => 'ReportFactory',
        '--module' => $this->module,
        '--model' => 'Report',
    ])->assertExitCode(0);

    $this->artisan('make:module-policy', [
        'name' => 'ReportPolicy',
        '--module' => $this->module,
        '--model' => 'Report',
    ])->assertExitCode(0);

    $this->artisan('make:module-migration', [
        'name' => 'create_reports_table',
        '--module' => $this->module,
        '--create' => 'reports',
    ])->assertExitCode(0);

    expect(File::exists($this->modulePath.'/src/Http/Controllers/ReportController.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Http/Requests/StoreReportRequest.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/database/factories/ReportFactory.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Policies/ReportPolicy.php'))->toBeTrue()
        ->and(File::glob($this->modulePath.'/database/migrations/*_create_reports_table.php'))->not->toBeEmpty();
});

test('module generators support the second-stage Laravel artifacts', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    $commands = [
        ['make:module-job', 'ProcessProducts'],
        ['make:module-event', 'ProductsImported'],
        ['make:module-listener', 'SendProductNotification'],
        ['make:module-notification', 'ProductImportedNotification'],
        ['make:module-mail', 'ProductReportMail'],
        ['make:module-command', 'ReindexProducts'],
        ['make:module-middleware', 'EnsureCatalogAccess'],
        ['make:module-rule', 'ValidProductCode'],
        ['make:module-observer', 'ProductObserver'],
        ['make:module-cast', 'MoneyCast'],
        ['make:module-enum', 'ProductStatus'],
        ['make:module-resource', 'ProductResource'],
    ];

    foreach ($commands as [$command, $name]) {
        $options = [
            'name' => $name,
            '--module' => $this->module,
        ];

        if ($command === 'make:module-observer') {
            $options['--model'] = 'Product';
        }

        if ($command === 'make:module-resource') {
            $options['--model'] = 'Product';
        }

        $this->artisan($command, $options)->assertExitCode(0);
    }

    expect(File::exists($this->modulePath.'/src/Jobs/ProcessProducts.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Events/ProductsImported.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Listeners/SendProductNotification.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Notifications/ProductImportedNotification.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Mail/ProductReportMail.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Console/Commands/ReindexProducts.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Http/Middleware/EnsureCatalogAccess.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Rules/ValidProductCode.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Observers/ProductObserver.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Casts/MoneyCast.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Enums/ProductStatus.php'))->toBeTrue()
        ->and(File::exists($this->modulePath.'/src/Http/Resources/ProductResource.php'))->toBeTrue();

    foreach (File::allFiles($this->modulePath.'/src') as $file) {
        $process = new Process(['php', '-l', $file->getPathname()]);
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    }
});

test('module model generation supports nested names, pivots, and force', function (): void {
    $this->artisan('make:module', ['name' => $this->module])
        ->assertExitCode(0);

    $this->artisan('make:module-model', [
        'name' => 'Admin/Product',
        '--module' => $this->module,
    ])->assertExitCode(0);

    $this->artisan('make:module-model', [
        'name' => 'Membership',
        '--module' => $this->module,
        '--pivot' => true,
        '--migration' => true,
    ])->assertExitCode(0);

    $productPath = $this->modulePath.'/src/Models/Admin/Product.php';

    expect(File::get($productPath))
        ->toContain('namespace Modules\\Catalog\\Models\\Admin;')
        ->and(File::get($this->modulePath.'/src/Models/Membership.php'))
        ->toContain('extends Pivot')
        ->and(File::glob($this->modulePath.'/database/migrations/*_create_membership_table.php'))->not->toBeEmpty();

    File::put($productPath, '<?php // original');

    $this->artisan('make:module-model', [
        'name' => 'Admin/Product',
        '--module' => $this->module,
        '--force' => true,
    ])->assertExitCode(0);

    expect(File::get($productPath))->toContain('class Product extends Model');
});
