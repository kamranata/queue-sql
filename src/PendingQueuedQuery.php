<?php

namespace QueueSql;

use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Bus;
use QueueSql\Jobs\DeleteRangeJob;

class PendingQueuedQuery
{
    public function __construct(
        private EloquentBuilder|QueryBuilder $builder,
        private QueueConfig $config,
    ) {}

    public function config(): QueueConfig
    {
        return $this->config;
    }

    public function builder(): EloquentBuilder|QueryBuilder
    {
        return $this->builder;
    }

    public function delete(): Batch
    {
        $snapshot = QuerySnapshot::capture($this->builder);
        $key = $snapshot->keyName();
        $ranges = (new RangePlanner($this->builder, $key))->plan($this->config->chunk);

        $tries = $this->config->tries;
        $jobs = array_map(function (array $range) use ($snapshot, $key, $tries) {
            $job = new DeleteRangeJob($snapshot, $range, $key);
            $job->tries = $tries;
            return $job;
        }, $ranges);

        $batch = Bus::batch($jobs)
            ->onQueue($this->config->onQueue)
            ->name('queue-sql:delete');

        if ($this->config->onConnection !== null) {
            $batch->onConnection($this->config->onConnection);
        }

        return $batch->dispatch();
    }
}
