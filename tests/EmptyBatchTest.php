<?php

namespace QueueSql\Tests;

use QueueSql\Tests\Support\User;

class EmptyBatchTest extends TestCase
{
    public function test_empty_batch_still_fires_then_and_finally(): void
    {
        // No rows at all, so no rows match the filter.
        config()->set('queue.default', 'sync');

        $ran = [];

        User::where('is_blocked', true)
            ->queue()
            ->delete()
            ->then(function () use (&$ran) { $ran[] = 'then'; })
            ->finally(function () use (&$ran) { $ran[] = 'finally'; })
            ->dispatch();

        $this->assertSame(['then', 'finally'], $ran);
    }
}
