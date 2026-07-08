<?php

namespace QueueSql;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use QueueSql\Jobs\DeleteRangeJob;
use QueueSql\Jobs\InsertChunkJob;
use QueueSql\Jobs\UpdateRangeJob;

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

    public function delete(): QueuedOperation
    {
        $snapshot = QuerySnapshot::capture($this->builder);
        $key = $snapshot->keyName();
        $builder = $this->builder;
        $ranges = $this->buildRanges($snapshot, $key);

        $tries = $this->config->tries;
        $backoff = $this->config->backoff;
        $jobs = array_map(function (?array $range) use ($snapshot, $key, $tries, $backoff) {
            $job = new DeleteRangeJob($snapshot, $range, $key);
            $job->tries = $tries;
            $job->backoff = $backoff;
            return $job;
        }, $ranges);

        return new QueuedOperation(
            jobs: $jobs,
            config: $this->config,
            operation: 'delete',
            countProbe: fn () => (clone $builder)->count(),
        );
    }

    public function update(array $values): QueuedOperation
    {
        $snapshot = QuerySnapshot::capture($this->builder);
        $key = $snapshot->keyName();
        $builder = $this->builder;
        $ranges = $this->buildRanges($snapshot, $key);

        $tries = $this->config->tries;
        $backoff = $this->config->backoff;
        $jobs = array_map(function (?array $range) use ($snapshot, $key, $values, $tries, $backoff) {
            $job = new UpdateRangeJob($snapshot, $range, $key, $values);
            $job->tries = $tries;
            $job->backoff = $backoff;
            return $job;
        }, $ranges);

        return new QueuedOperation(
            jobs: $jobs,
            config: $this->config,
            operation: 'update',
            countProbe: fn () => (clone $builder)->count(),
        );
    }

    public function insert(array $rows): QueuedOperation
    {
        $builder = $this->builder;

        if ($builder instanceof EloquentBuilder) {
            $model = get_class($builder->getModel());
            $connection = $builder->getModel()->getConnectionName();
            $table = null;
        } else {
            $model = null;
            $connection = $builder->getConnection()->getName();
            $table = $builder->from;
        }

        $parts = array_chunk($rows, max($this->config->chunk, 1));

        $tries = $this->config->tries;
        $backoff = $this->config->backoff;
        $jobs = array_map(function (array $part) use ($model, $connection, $table, $tries, $backoff) {
            $job = new InsertChunkJob($model, $connection, $table, $part);
            $job->tries = $tries;
            $job->backoff = $backoff;
            return $job;
        }, $parts);

        return new QueuedOperation(
            jobs: $jobs,
            config: $this->config,
            operation: 'insert',
            countProbe: fn () => count($rows),
        );
    }

    private function buildRanges(QuerySnapshot $snapshot, string $key): array
    {
        if (! $snapshot->canFanOut()) {
            return [null]; // single job, no whereBetween
        }

        return (new RangePlanner($this->builder, $key))->plan($this->config->chunk) ?: [];
    }
}
