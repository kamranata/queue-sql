<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use QueueSql\Tests\Support\User;

class BackoffTest extends TestCase
{
    private function seedRows(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => $i % 2 === 0];
        }
        User::insert($rows);
    }

    public function test_delete_applies_configured_backoff_to_every_job(): void
    {
        $this->seedRows(10); // ids 1..10
        Bus::fake();

        User::where('is_blocked', true)->queue(chunk: 4, backoff: 30)->delete()->dispatch();

        Bus::assertBatched(fn ($batch) => $batch->jobs->every(fn ($j) => $j->backoff === 30));
    }
}
