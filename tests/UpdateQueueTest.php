<?php

namespace QueueSql\Tests;

use QueueSql\Tests\Support\User;

class UpdateQueueTest extends TestCase
{
    public function test_update_applies_values_to_matching_rows(): void
    {
        $rows = [];
        for ($i = 1; $i <= 6; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => true];
        }
        User::insert($rows);

        config()->set('queue.default', 'sync');
        User::where('is_blocked', true)
            ->queue(chunk: 4)
            ->update(['is_blocked' => false])
            ->dispatch();

        $this->assertSame(0, User::where('is_blocked', true)->count());
        $this->assertSame(6, User::where('is_blocked', false)->count());
    }
}
