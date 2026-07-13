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
        public readonly ?int $maxJobs = null,
    ) {}

    public static function make(
        ?int $chunk = null,
        ?int $tries = null,
        int|array|null $backoff = null,
        ?string $onConnection = null,
        ?string $onQueue = null,
        ?int $throttle = null,
        ?int $delay = null,
        ?int $maxJobs = null,
    ): self {
        // chunk and maxJobs are two ways to size the same fan-out; accepting both is
        // ambiguous. maxJobs is opt-in and has NO config default, so it never competes
        // with the config-driven chunk default.
        if ($chunk !== null && $maxJobs !== null) {
            throw new \InvalidArgumentException(
                'queue(): pass either chunk or maxJobs, not both.'
            );
        }

        // Precedence for every param: explicit arg > config default > built-in fallback.
        return new self(
            chunk: $chunk ?? (int) config('queue-sql.chunk', 1000),
            tries: $tries ?? (int) config('queue-sql.tries', 1),
            backoff: $backoff ?? config('queue-sql.backoff', 0),
            onConnection: $onConnection ?? config('queue-sql.connection'),
            onQueue: $onQueue ?? config('queue-sql.queue'),
            throttle: $throttle ?? config('queue-sql.throttle'),
            delay: $delay ?? config('queue-sql.delay'),
            maxJobs: $maxJobs,
        );
    }
}
