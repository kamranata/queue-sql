<?php

namespace QueueSql\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class StatusCommand extends Command
{
    protected $signature = 'queue-sql:status {batch? : Batch id; omit to list all queue-sql batches}';

    protected $description = 'Show queue-sql batch status — list all, or one batch by id.';

    public function handle(): int
    {
        $id = $this->argument('batch');

        return $id !== null ? $this->showOne($id) : $this->listAll();
    }

    private function showOne(string $id): int
    {
        $batch = Bus::findBatch($id);

        if ($batch === null) {
            $this->error("Batch [{$id}] not found.");

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['id', $batch->id],
            ['name', $batch->name],
            ['total', $batch->totalJobs],
            ['pending', $batch->pendingJobs],
            ['failed', $batch->failedJobs],
            ['progress', $batch->progress() . '%'],
            ['finished', $batch->finished() ? 'yes' : 'no'],
            ['cancelled', $batch->cancelled() ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }

    private function listAll(): int
    {
        // There is no Bus API to enumerate batches, so read the batching table directly
        // and filter by the queue-sql name prefix.
        $rows = DB::connection(config('queue.batching.database'))
            ->table(config('queue.batching.table', 'job_batches'))
            ->where('name', 'like', 'queue-sql:%')
            ->orderByDesc('created_at')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No queue-sql batches found.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'name', 'total', 'pending', 'failed', 'progress'],
            $rows->map(function ($r) {
                $progress = $r->total_jobs > 0
                    ? (int) round(($r->total_jobs - $r->pending_jobs) / $r->total_jobs * 100)
                    : 0;

                return [$r->id, $r->name, $r->total_jobs, $r->pending_jobs, $r->failed_jobs, "{$progress}%"];
            })->all()
        );

        return self::SUCCESS;
    }
}
