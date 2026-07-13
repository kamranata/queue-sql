<?php

namespace QueueSql\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use QueueSql\QuerySnapshot;

class DeleteRangeJob implements ShouldQueue
{
    use Batchable, Dispatchable, HasQueueSqlTags, InteractsWithQueue, Queueable;

    /** Retry attempts; a public property is how the queue worker reads tries. */
    public ?int $tries = null;

    /** Seconds (or array of seconds) to wait between retries; read by the queue worker. */
    public int|array|null $backoff = null;

    public function __construct(
        public QuerySnapshot $snapshot,
        public ?array $range,
        public string $keyName,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $query = $this->snapshot->restore();

        if ($this->range !== null) {
            [$start, $end] = $this->range;
            $query->whereBetween($this->keyName, [$start, $end]);
        }

        $query->delete();
    }

    public function tags(): array
    {
        return $this->queueSqlTags('delete', $this->snapshot->tableName());
    }
}
