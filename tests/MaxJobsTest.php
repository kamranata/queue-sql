<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use QueueSql\Tests\Support\Token;
use QueueSql\Tests\Support\User;

class MaxJobsTest extends TestCase
{
    public function test_max_jobs_caps_fanout_job_count(): void
    {
        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => true];
        }
        User::insert($rows); // ids 1..100

        Bus::fake();

        User::where('is_blocked', true)->queue(maxJobs: 4)->delete()->dispatch();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 4);
    }

    public function test_max_jobs_never_exceeds_cap_when_larger_than_span(): void
    {
        User::insert([
            ['name' => 'a', 'is_blocked' => true],
            ['name' => 'b', 'is_blocked' => true],
            ['name' => 'c', 'is_blocked' => true],
        ]); // ids 1..3, span 3

        Bus::fake();

        User::where('is_blocked', true)->queue(maxJobs: 10)->delete()->dispatch();

        // span (3) < maxJobs (10) -> chunk 1 -> 3 ranges, still <= cap
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
    }

    public function test_max_jobs_on_insert_caps_chunk_count(): void
    {
        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $rows[] = ['name' => "r{$i}", 'is_blocked' => false];
        }

        Bus::fake();

        DB::table('users')->queue(maxJobs: 3)->insert($rows)->dispatch();

        // ceil(10 / 3) = 4 rows per chunk -> ceil(10 / 4) = 3 chunks
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
    }

    public function test_max_jobs_ignored_when_fanout_not_possible(): void
    {
        Token::insert([
            ['uuid' => 'a', 'revoked' => true],
            ['uuid' => 'b', 'revoked' => true],
        ]);

        Bus::fake();

        Token::where('revoked', true)->queue(maxJobs: 5)->delete()->dispatch();

        // non-integer PK -> single-job fallback, maxJobs is moot
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    public function test_chunk_and_max_jobs_together_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        User::query()->queue(chunk: 10, maxJobs: 4);
    }
}
