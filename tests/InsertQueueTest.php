<?php

namespace QueueSql\Tests;

use QueueSql\Tests\Support\User;

class InsertQueueTest extends TestCase
{
    public function test_insert_splits_rows_across_jobs_and_persists_all(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => false];
        }

        config()->set('queue.default', 'sync');
        $plan = User::query()->queue(chunk: 4)->insert($rows)->dryRun();

        $this->assertSame(3, $plan['jobs']); // 4 + 4 + 2

        User::query()->queue(chunk: 4)->insert($rows)->dispatch();
        $this->assertSame(10, User::count());
    }
}
