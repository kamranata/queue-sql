<?php

namespace QueueSql\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class InsertChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    /** Retry attempts; a public property is how the queue worker reads tries. */
    public ?int $tries = null;

    public function __construct(
        public ?string $model,
        public ?string $connectionName,   // NOT $connection — that collides with Queueable::$connection
        public ?string $table,
        public array $rows,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->model !== null) {
            (new $this->model)->setConnection($this->connectionName)->newQuery()->insert($this->rows);
            return;
        }

        DB::connection($this->connectionName)->table($this->table)->insert($this->rows);
    }
}
