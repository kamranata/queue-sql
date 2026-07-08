<?php

namespace QueueSql;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

        $macro = function (
            ?int $chunk = null,
            int $tries = 1,
            int|array $backoff = 0,
            ?string $onConnection = null,
            ?string $onQueue = null,
            ?int $throttle = null,
            ?int $delay = null,
            bool $dryRun = false,
        ) {
            /** @var EloquentBuilder|QueryBuilder $this */
            return new PendingQueuedQuery($this, QueueConfig::make(
                chunk: $chunk, tries: $tries, backoff: $backoff,
                onConnection: $onConnection, onQueue: $onQueue,
                throttle: $throttle, delay: $delay, dryRun: $dryRun,
            ));
        };

        QueryBuilder::macro('queue', $macro);
        EloquentBuilder::macro('queue', $macro);
    }
}
