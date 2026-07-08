<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use QueueSql\Jobs\DeleteRangeJob;
use QueueSql\Tests\Support\User;

class DeleteQueueTest extends TestCase
{
    private function seedRows(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => $i % 2 === 0];
        }
        User::insert($rows);
    }

    public function test_delete_dispatches_one_job_per_range(): void
    {
        $this->seedRows(10); // ids 1..10
        Bus::fake();

        User::where('is_blocked', true)->queue(chunk: 4)->delete();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
    }

    public function test_delete_actually_removes_matching_rows_when_run(): void
    {
        $this->seedRows(10); // 5 blocked (even ids), 5 not

        // Run jobs synchronously.
        config()->set('queue.default', 'sync');
        User::where('is_blocked', true)->queue(chunk: 4)->delete();

        $this->assertSame(5, User::count());
        $this->assertSame(0, User::where('is_blocked', true)->count());
    }
}
