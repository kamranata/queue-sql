<?php

namespace QueueSql;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class QueuedOperation
{
    private $thenCb = null;
    private $catchCb = null;
    private $finallyCb = null;
    private bool $allowFailures = false;

    public function __construct(
        private array $jobs,
        private QueueConfig $config,
        private string $operation,
        private $countProbe = null,
    ) {}

    public function then(callable $cb): self { $this->thenCb = $cb; return $this; }
    public function catch(callable $cb): self { $this->catchCb = $cb; return $this; }
    public function finally(callable $cb): self { $this->finallyCb = $cb; return $this; }

    public function allowFailures(bool $allow = true): self
    {
        $this->allowFailures = $allow;
        return $this;
    }

    public function dispatch(): Batch
    {
        $this->applyThrottle();

        $batch = Bus::batch($this->jobs)
            ->name('queue-sql:' . $this->operation);

        // PendingBatch::onConnection()/onQueue() reject null in Laravel 13; a null
        // config value means "use the default", so only set them when non-null.
        if ($this->config->onConnection !== null) {
            $batch->onConnection($this->config->onConnection);
        }
        if ($this->config->onQueue !== null) {
            $batch->onQueue($this->config->onQueue);
        }

        if ($this->allowFailures) {
            $batch->allowFailures();
        }
        if ($this->thenCb)    { $batch->then($this->thenCb); }
        if ($this->catchCb)   { $batch->catch($this->catchCb); }
        if ($this->finallyCb) { $batch->finally($this->finallyCb); }

        return $batch->dispatch();
    }

    public function dryRun(): array
    {
        return [
            'operation' => $this->operation,
            'jobs' => count($this->jobs),
            'ranges' => count($this->jobs),
            'estimatedRows' => $this->countProbe ? (int) ($this->countProbe)() : null,
        ];
    }

    private function applyThrottle(): void
    {
        $base = $this->config->delay ?? 0;
        $throttle = $this->config->throttle;

        foreach ($this->jobs as $i => $job) {
            $extra = $throttle ? intdiv($i, max($throttle, 1)) : 0;
            $seconds = $base + $extra;
            if ($seconds > 0 && method_exists($job, 'delay')) {
                $job->delay($seconds);
            }
        }
    }
}
