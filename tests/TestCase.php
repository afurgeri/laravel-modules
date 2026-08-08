<?php

namespace Tests;

use Modules\LaravelModules\LaravelModulesServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelModulesServiceProvider::class];
    }
}
