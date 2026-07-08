<?php

namespace QueueSql\Tests;

use QueueSql\PendingQueuedQuery;
use QueueSql\Tests\Support\User;

class QueueMacroTest extends TestCase
{
    public function test_eloquent_queue_returns_pending_with_config(): void
    {
        $pending = User::where('is_blocked', true)->queue(chunk: 50, tries: 3);

        $this->assertInstanceOf(PendingQueuedQuery::class, $pending);
        $this->assertSame(50, $pending->config()->chunk);
        $this->assertSame(3, $pending->config()->tries);
    }

    public function test_query_builder_queue_is_available(): void
    {
        $pending = $this->app['db']->table('users')->where('is_blocked', true)->queue();
        $this->assertInstanceOf(PendingQueuedQuery::class, $pending);
    }
}
