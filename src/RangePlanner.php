<?php

namespace QueueSql;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class RangePlanner
{
    public function __construct(
        private EloquentBuilder|QueryBuilder $builder,
        private string $keyName,
    ) {}

    public function plan(int $chunk): array
    {
        [$min, $max] = $this->bounds();
        if ($min === null) {
            return [];
        }

        return $this->ranges($min, $max, $chunk);
    }

    /**
     * Size the chunk so the fan-out yields at most $maxJobs ranges.
     * chunk = ceil(span / maxJobs) guarantees ceil(span / chunk) <= maxJobs.
     */
    public function planForMaxJobs(int $maxJobs): array
    {
        [$min, $max] = $this->bounds();
        if ($min === null) {
            return [];
        }

        $span = $max - $min + 1;
        $chunk = (int) max(1, (int) ceil($span / max($maxJobs, 1)));

        return $this->ranges($min, $max, $chunk);
    }

    /** @return array{0: ?int, 1: ?int} */
    private function bounds(): array
    {
        $min = (clone $this->builder)->min($this->keyName);
        if ($min === null) {
            return [null, null];
        }

        return [(int) $min, (int) (clone $this->builder)->max($this->keyName)];
    }

    private function ranges(int $min, int $max, int $chunk): array
    {
        $ranges = [];
        for ($start = $min; $start <= $max; $start += $chunk) {
            $ranges[] = [$start, min($start + $chunk - 1, $max)];
        }

        return $ranges;
    }
}
