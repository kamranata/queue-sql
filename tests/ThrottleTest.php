<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use QueueSql\Tests\Support\User;

class ThrottleTest extends TestCase
{
    public function test_throttle_staggers_job_delays(): void
    {
        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => true];
        }
        User::insert($rows); // ids 1..12 -> chunk 3 -> 4 jobs

        Bus::fake();

        User::where('is_blocked', true)
            ->queue(chunk: 3, throttle: 2, delay: 5)
            ->delete()
            ->dispatch();

        Bus::assertBatched(function ($batch) {
            $delays = $batch->jobs->map(fn ($j) => $j->delay)->all();
            // base delay 5 + intdiv(index, throttle=2):
            // index 0->5, 1->5, 2->6, 3->6
            return $delays === [5, 5, 6, 6];
        });
    }
}
