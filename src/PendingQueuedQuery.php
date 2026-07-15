<?php

namespace QueueSql;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use QueueSql\Jobs\DeleteRangeJob;
use QueueSql\Jobs\InsertChunkJob;
use QueueSql\Jobs\UpdateRangeJob;
use QueueSql\Jobs\UpsertChunkJob;

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
            table: $snapshot->tableName(),
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
            table: $snapshot->tableName(),
        );
    }

    public function insert(array $rows): QueuedOperation
    {
        [$model, $connection, $table, $tableName] = $this->rowTarget();

        $jobs = array_map(function (array $part) use ($model, $connection, $table) {
            return $this->tune(new InsertChunkJob($model, $connection, $table, $part));
        }, $this->chunkRows($rows));

        return new QueuedOperation(
            jobs: $jobs,
            config: $this->config,
            operation: 'insert',
            countProbe: fn () => count($rows),
            table: $tableName,
        );
    }

    public function upsert(array $values, array|string $uniqueBy, ?array $update = null): QueuedOperation
    {
        [$model, $connection, $table, $tableName] = $this->rowTarget();

        $jobs = array_map(function (array $part) use ($model, $connection, $table, $uniqueBy, $update) {
            return $this->tune(new UpsertChunkJob($model, $connection, $table, $part, $uniqueBy, $update));
        }, $this->chunkRows($values));

        return new QueuedOperation(
            jobs: $jobs,
            config: $this->config,
            operation: 'upsert',
            countProbe: fn () => count($values),
            table: $tableName,
        );
    }

    /**
     * Resolve the fan-out target for row-array terminals (insert/upsert).
     *
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string} [model, connection, table, tableName]
     */
    private function rowTarget(): array
    {
        $builder = $this->builder;

        if ($builder instanceof EloquentBuilder) {
            $model = get_class($builder->getModel());

            return [$model, $builder->getModel()->getConnectionName(), null, $builder->getModel()->getTable()];
        }

        return [null, $builder->getConnection()->getName(), $builder->from, $builder->from];
    }

    /** Split a row array into chunks sized by chunk, or by maxJobs when set. */
    private function chunkRows(array $rows): array
    {
        $chunkSize = $this->config->maxJobs !== null
            ? (int) max(1, (int) ceil(count($rows) / max($this->config->maxJobs, 1)))
            : max($this->config->chunk, 1);

        return array_chunk($rows, $chunkSize);
    }

    /** Apply the shared per-job retry settings. */
    private function tune(object $job): object
    {
        $job->tries = $this->config->tries;
        $job->backoff = $this->config->backoff;

        return $job;
    }

    private function buildRanges(QuerySnapshot $snapshot, string $key): array
    {
        if (! $snapshot->canFanOut()) {
            return [null]; // single job, no whereBetween
        }

        $planner = new RangePlanner($this->builder, $key);

        return ($this->config->maxJobs !== null
            ? $planner->planForMaxJobs($this->config->maxJobs)
            : $planner->plan($this->config->chunk)) ?: [];
    }
}
