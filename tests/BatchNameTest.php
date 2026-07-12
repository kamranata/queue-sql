<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use QueueSql\Tests\Support\User;

class BatchNameTest extends TestCase
{
    public function test_delete_batch_name_includes_table(): void
    {
        Bus::fake();

        User::where('is_blocked', true)->queue(chunk: 100)->delete()->dispatch();

        Bus::assertBatched(fn ($batch) => $batch->name === 'queue-sql:delete:users');
    }

    public function test_update_batch_name_includes_table(): void
    {
        Bus::fake();

        User::where('is_blocked', true)->queue(chunk: 100)->update(['is_blocked' => false])->dispatch();

        Bus::assertBatched(fn ($batch) => $batch->name === 'queue-sql:update:users');
    }

    public function test_insert_batch_name_includes_table_from_query_builder(): void
    {
        Bus::fake();

        DB::table('users')->queue(chunk: 100)->insert([['name' => 'a', 'is_blocked' => false]])->dispatch();

        Bus::assertBatched(fn ($batch) => $batch->name === 'queue-sql:insert:users');
    }

    public function test_dry_run_reports_table_and_keeps_pure_operation(): void
    {
        $plan = User::where('is_blocked', true)->queue(chunk: 100)->delete()->dryRun();

        $this->assertSame('delete', $plan['operation']);
        $this->assertSame('users', $plan['table']);
    }
}
