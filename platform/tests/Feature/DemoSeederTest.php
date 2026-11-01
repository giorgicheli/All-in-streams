<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_cannot_create_accounts_with_known_demo_credentials(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->app->make(\Database\Seeders\DatabaseSeeder::class)->run();
            $this->fail('Production must reject the demo seeder before creating accounts.');
        } catch (LogicException $exception) {
            $this->assertSame('Demo data may only be seeded in local or testing environments.', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertDatabaseCount('users', 0);
    }
}
