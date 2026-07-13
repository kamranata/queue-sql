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
        // Default is in-memory SQLite (fast, zero-setup). CI's cross-database job sets
        // DB_CONNECTION=pgsql|mysql to exercise real drivers, since MIN/MAX, whereBetween
        // boundaries, and grammar all vary by engine.
        $connection = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', match ($connection) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'queue_sql_test'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'postgres'),
                'prefix' => '',
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'queue_sql_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', 'root'),
                'prefix' => '',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        });
        $app['config']->set('queue.batching.database', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        // Runs per test. On :memory: SQLite each test gets a fresh DB, but a persistent
        // Postgres/MySQL keeps tables between tests — so drop-then-create to stay
        // idempotent regardless of driver.
        $schema->dropIfExists('users');
        $schema->create('users', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->boolean('is_blocked')->default(false);
        });

        $schema->dropIfExists('tokens');
        $schema->create('tokens', function ($t) {
            $t->string('uuid')->primary();
            $t->boolean('revoked')->default(false);
        });

        // job_batches (required by Bus::batch). Create once via the version-correct
        // artisan command; on a persistent DB across tests just clear its rows so a
        // prior test's dispatched batch can't leak into the next.
        if (! $schema->hasTable('job_batches')) {
            $this->artisan('queue:batches-table');
            $this->artisan('migrate');
        } else {
            $this->app['db']->connection()->table('job_batches')->truncate();
        }
    }
}
