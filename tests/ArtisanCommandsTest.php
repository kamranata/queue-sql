<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use QueueSql\Tests\Support\User;

class ArtisanCommandsTest extends TestCase
{
    private function seedUsers(int $n): void
    {
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => true];
        }
        User::insert($rows);
    }

    public function test_status_reports_when_no_batches(): void
    {
        $this->artisan('queue-sql:status')
            ->expectsOutputToContain('No queue-sql batches found.')
            ->assertSuccessful();
    }

    public function test_status_lists_queue_sql_batches(): void
    {
        $this->seedUsers(4);
        User::where('is_blocked', true)->queue(chunk: 2)->delete()->dispatch();

        $this->artisan('queue-sql:status')
            ->expectsOutputToContain('queue-sql:delete:users')
            ->assertSuccessful();
    }

    public function test_status_excludes_non_queue_sql_batches(): void
    {
        DB::table('job_batches')->insert([
            'id' => 'other-1',
            'name' => 'some-other-batch',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => json_encode([]),
            'options' => serialize([]),
            'cancelled_at' => null,
            'created_at' => 1700000000,
            'finished_at' => null,
        ]);

        $this->artisan('queue-sql:status')
            ->doesntExpectOutputToContain('some-other-batch')
            ->assertSuccessful();
    }

    public function test_status_detail_for_a_batch(): void
    {
        $this->seedUsers(4);
        $batch = User::where('is_blocked', true)->queue(chunk: 2)->delete()->dispatch();

        $this->artisan('queue-sql:status', ['batch' => $batch->id])
            ->expectsOutputToContain('queue-sql:delete:users')
            ->assertSuccessful();
    }

    public function test_status_detail_unknown_id_fails(): void
    {
        $this->artisan('queue-sql:status', ['batch' => 'nope'])
            ->assertFailed();
    }

    public function test_cancel_marks_batch_cancelled(): void
    {
        $this->seedUsers(4);
        $batch = User::where('is_blocked', true)->queue(chunk: 2)->delete()->dispatch();

        $this->assertFalse(Bus::findBatch($batch->id)->cancelled());

        $this->artisan('queue-sql:cancel', ['batch' => $batch->id])
            ->assertSuccessful();

        $this->assertTrue(Bus::findBatch($batch->id)->cancelled());
    }

    public function test_cancel_unknown_id_fails(): void
    {
        $this->artisan('queue-sql:cancel', ['batch' => 'nope'])
            ->assertFailed();
    }
}
