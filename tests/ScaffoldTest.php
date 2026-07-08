<?php

namespace QueueSql\Tests;

class ScaffoldTest extends TestCase
{
    public function test_config_default_chunk_is_loaded(): void
    {
        $this->assertSame(1000, config('queue-sql.chunk'));
    }
}
