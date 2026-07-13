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
        ?int $tries = null,
        int|array|null $backoff = null,
        ?string $onConnection = null,
        ?string $onQueue = null,
        ?int $throttle = null,
        ?int $delay = null,
    ): self {
        // Precedence for every param: explicit arg > config default > built-in fallback.
        return new self(
            chunk: $chunk ?? (int) config('queue-sql.chunk', 1000),
            tries: $tries ?? (int) config('queue-sql.tries', 1),
            backoff: $backoff ?? config('queue-sql.backoff', 0),
            onConnection: $onConnection ?? config('queue-sql.connection'),
            onQueue: $onQueue ?? config('queue-sql.queue'),
            throttle: $throttle ?? config('queue-sql.throttle'),
            delay: $delay ?? config('queue-sql.delay'),
        );
    }
}
