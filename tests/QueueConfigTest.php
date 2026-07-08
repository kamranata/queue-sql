<?php

namespace QueueSql\Tests;

use QueueSql\QueueConfig;

class QueueConfigTest extends TestCase
{
    public function test_defaults_come_from_config(): void
    {
        $c = QueueConfig::make();
        $this->assertSame(1000, $c->chunk);
        $this->assertSame(1, $c->tries);
    }

    public function test_named_overrides_win(): void
    {
        $c = QueueConfig::make(chunk: 500, tries: 3, onQueue: 'cleanup');
        $this->assertSame(500, $c->chunk);
        $this->assertSame(3, $c->tries);
        $this->assertSame('cleanup', $c->onQueue);
    }
}
