<?php

namespace QueueSql;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
}
