<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use QueueSql\Tests\Support\User;

class UpsertQueueTest extends TestCase
{
    public function test_upsert_inserts_new_and_updates_existing(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'old', 'is_blocked' => false]);

        DB::table('users')
            ->queue(chunk: 10)
            ->upsert(
                [
                    ['id' => 1, 'name' => 'new1', 'is_blocked' => true],
                    ['id' => 2, 'name' => 'new2', 'is_blocked' => true],
                ],
                ['id'],
                ['name', 'is_blocked']
            )
            ->dispatch();

        $this->assertSame('new1', DB::table('users')->where('id', 1)->value('name'));
        $this->assertSame('new2', DB::table('users')->where('id', 2)->value('name'));
        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_upsert_fans_out_over_rows(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = ['id' => $i, 'name' => "u{$i}", 'is_blocked' => false];
        }

        Bus::fake();

        DB::table('users')->queue(chunk: 4)->upsert($rows, ['id'], ['name'])->dispatch();

        // 10 rows / chunk 4 -> 3 jobs
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
    }

    public function test_upsert_respects_max_jobs(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = ['id' => $i, 'name' => "u{$i}", 'is_blocked' => false];
        }

        Bus::fake();

        DB::table('users')->queue(maxJobs: 3)->upsert($rows, ['id'], ['name'])->dispatch();

        // ceil(10/3)=4 per chunk -> ceil(10/4)=3 chunks
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
    }

    public function test_upsert_dry_run_reports_table(): void
    {
        $plan = DB::table('users')
            ->queue(chunk: 10)
            ->upsert([['id' => 1, 'name' => 'a']], ['id'], ['name'])
            ->dryRun();

        $this->assertSame('upsert', $plan['operation']);
        $this->assertSame('users', $plan['table']);
    }
}
