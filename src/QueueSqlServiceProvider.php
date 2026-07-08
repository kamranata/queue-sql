<?php

namespace QueueSql;

use Illuminate\Support\ServiceProvider;

class QueueSqlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/queue-sql.php', 'queue-sql');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/queue-sql.php' => config_path('queue-sql.php'),
        ], 'queue-sql-config');
    }
}
