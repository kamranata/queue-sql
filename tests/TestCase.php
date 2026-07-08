<?php

namespace QueueSql\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use QueueSql\QueueSqlServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [QueueSqlServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->boolean('is_blocked')->default(false);
        });
        // job_batches table required by Bus::batch
        $this->artisan('queue:batches-table');
        $this->artisan('migrate');
    }
}
