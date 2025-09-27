<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use HipstersgDemo\LaravelUserDiscountsPackage\DiscountServiceProvider;


/**
 * @method void loadLaravelMigrations()
 * @method void loadMigrationsFrom()
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase; // using RefreshDatabase trait for better transaction handling

    /**
     * Load Laravel migrations manually.
     *
     * @param string|null $database
     * @return void
     */
    public function loadLaravelMigrations($database = null): void
    {
        $args = ['--path' => 'database/migrations'];
        if ($database) {
            $args['--database'] = $database;
        }
        $this->artisan('migrate', $args);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Removed custom migration loading to avoid conflicts with RefreshDatabase
    }

    protected function getPackageProviders($app)
    {
        return [
            DiscountServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Use in-memory SQLite
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
