<?php

namespace QueueSql\Tests;

use QueueSql\RangePlanner;
use QueueSql\Tests\Support\User;

class RangePlannerTest extends TestCase
{
    private function seedRows(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => true];
        }
        User::insert($rows);
    }

    public function test_ranges_cover_min_to_max_in_chunks(): void
    {
        $this->seedRows(10); // ids 1..10
        $planner = new RangePlanner(User::where('is_blocked', true), 'id');

        $this->assertSame([[1, 4], [5, 8], [9, 10]], $planner->plan(4));
    }

    public function test_empty_result_returns_no_ranges(): void
    {
        $planner = new RangePlanner(User::where('is_blocked', true), 'id');
        $this->assertSame([], $planner->plan(4));
    }
}
