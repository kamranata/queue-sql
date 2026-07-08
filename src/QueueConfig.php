<?php

namespace QueueSql;

class QueueConfig
{
    public function __construct(
        public readonly int $chunk,
        public readonly int $tries,
        public readonly int|array $backoff,
        public readonly ?string $onConnection,
        public readonly ?string $onQueue,
        public readonly ?int $throttle,
        public readonly ?int $delay,
    ) {}

    public static function make(
        ?int $chunk = null,
        int $tries = 1,
        int|array $backoff = 0,
        ?string $onConnection = null,
        ?string $onQueue = null,
        ?int $throttle = null,
        ?int $delay = null,
    ): self {
        return new self(
            chunk: $chunk ?? (int) config('queue-sql.chunk', 1000),
            tries: $tries,
            backoff: $backoff,
            onConnection: $onConnection ?? config('queue-sql.connection'),
            onQueue: $onQueue ?? config('queue-sql.queue'),
            throttle: $throttle,
            delay: $delay,
        );
    }
}
