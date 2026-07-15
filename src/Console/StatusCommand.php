<?php

namespace QueueSql\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class StatusCommand extends Command
{
    protected $signature = 'queue-sql:status
        {batch? : Batch id; omit to list all queue-sql batches}
        {--json : Output machine-readable JSON instead of a table}
        {--watch : Refresh continuously (interactive terminals only)}
        {--interval=2 : Seconds between refreshes when --watch is set}';

    protected $description = 'Show queue-sql batch status — list all, or one batch by id.';

    public function handle(): int
    {
        // Only loop when writing to a real TTY. A non-terminal stream (tests, pipes, cron,
        // redirects) renders once and exits so it never hangs — and never emits control codes.
        $loop = $this->watchingLiveTerminal();

        do {
            if ($loop) {
                $this->output->write("\033[2J\033[H"); // clear screen + cursor home
            }

            $exit = $this->render();

            if (! $loop) {
                return $exit;
            }

            sleep(max(1, (int) $this->option('interval')));
        } while (true);
    }

    private function watchingLiveTerminal(): bool
    {
        if (! $this->option('watch')) {
            return false;
        }

        $output = $this->output->getOutput();

        return method_exists($output, 'getStream')
            && is_resource($stream = $output->getStream())
            && stream_isatty($stream);
    }

    private function render(): int
    {
        $id = $this->argument('batch');

        return $id !== null ? $this->showOne($id) : $this->listAll();
    }

    private function showOne(string $id): int
    {
        $batch = Bus::findBatch($id);

        if ($batch === null) {
            $this->option('json')
                ? $this->line(json_encode(['error' => "Batch [{$id}] not found."]))
                : $this->error("Batch [{$id}] not found.");

            return self::FAILURE;
        }

        $data = [
            'id' => $batch->id,
            'name' => $batch->name,
            'total' => $batch->totalJobs,
            'pending' => $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($data));

            return self::SUCCESS;
        }

        $this->table(['Field', 'Value'], [
            ['id', $data['id']],
            ['name', $data['name']],
            ['total', $data['total']],
            ['pending', $data['pending']],
            ['failed', $data['failed']],
            ['progress', $data['progress'] . '%'],
            ['finished', $data['finished'] ? 'yes' : 'no'],
            ['cancelled', $data['cancelled'] ? 'yes' : 'no'],
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

        $data = $rows->map(function ($r) {
            $progress = $r->total_jobs > 0
                ? (int) round(($r->total_jobs - $r->pending_jobs) / $r->total_jobs * 100)
                : 0;

            return [
                'id' => $r->id,
                'name' => $r->name,
                'total' => $r->total_jobs,
                'pending' => $r->pending_jobs,
                'failed' => $r->failed_jobs,
                'progress' => $progress,
            ];
        })->all();

        if ($this->option('json')) {
            $this->line(json_encode(array_values($data)));

            return self::SUCCESS;
        }

        if ($data === []) {
            $this->info('No queue-sql batches found.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'name', 'total', 'pending', 'failed', 'progress'],
            array_map(fn ($r) => [$r['id'], $r['name'], $r['total'], $r['pending'], $r['failed'], "{$r['progress']}%"], $data)
        );

        return self::SUCCESS;
    }
}
