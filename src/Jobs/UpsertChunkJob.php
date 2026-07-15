<?php

namespace QueueSql\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class UpsertChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, HasQueueSqlTags, InteractsWithQueue, Queueable;

    /** Retry attempts; a public property is how the queue worker reads tries. */
    public ?int $tries = null;

    /** Seconds (or array of seconds) to wait between retries; read by the queue worker. */
    public int|array|null $backoff = null;

    public function __construct(
        public ?string $model,
        public ?string $connectionName,   // NOT $connection — that collides with Queueable::$connection
        public ?string $table,
        public array $rows,
        public array|string $uniqueBy,
        public ?array $update,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->model !== null) {
            (new $this->model)->setConnection($this->connectionName)->newQuery()
                ->upsert($this->rows, $this->uniqueBy, $this->update);

            return;
        }

        DB::connection($this->connectionName)->table($this->table)
            ->upsert($this->rows, $this->uniqueBy, $this->update);
    }

    public function tags(): array
    {
        $table = $this->table ?? ($this->model !== null ? (new $this->model)->getTable() : null);

        return $this->queueSqlTags('upsert', $table);
    }
}
