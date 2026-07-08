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
        $min = (clone $this->builder)->min($this->keyName);
        if ($min === null) {
            return [];
        }
        $min = (int) $min;
        $max = (int) (clone $this->builder)->max($this->keyName);

        $ranges = [];
        for ($start = $min; $start <= $max; $start += $chunk) {
            $ranges[] = [$start, min($start + $chunk - 1, $max)];
        }

        return $ranges;
    }
}
