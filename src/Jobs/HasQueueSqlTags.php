<?php

namespace QueueSql\Jobs;

trait HasQueueSqlTags
{
    /**
     * Horizon reads tags() to group jobs in its dashboard. Mirror the batch-name
     * identity: queue-sql, queue-sql:{operation}, queue-sql:{operation}:{table}.
     */
    protected function queueSqlTags(string $operation, ?string $table): array
    {
        return array_values(array_filter([
            'queue-sql',
            "queue-sql:{$operation}",
            $table !== null ? "queue-sql:{$operation}:{$table}" : null,
        ]));
    }
}
