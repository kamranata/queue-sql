<?php

namespace QueueSql\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class CancelCommand extends Command
{
    protected $signature = 'queue-sql:cancel {batch : Batch id to cancel}';

    protected $description = 'Cancel a running queue-sql batch by id.';

    public function handle(): int
    {
        $id = $this->argument('batch');
        $batch = Bus::findBatch($id);

        if ($batch === null) {
            $this->error("Batch [{$id}] not found.");

            return self::FAILURE;
        }

        $batch->cancel();
        $this->info("Batch [{$id}] cancelled.");

        return self::SUCCESS;
    }
}
