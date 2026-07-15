<?php

namespace QueueSql\Tests;

use QueueSql\Jobs\DeleteRangeJob;
use QueueSql\Jobs\InsertChunkJob;
use QueueSql\Jobs\UpdateRangeJob;
use QueueSql\Jobs\UpsertChunkJob;
use QueueSql\QuerySnapshot;
use QueueSql\Tests\Support\User;

class HorizonTagsTest extends TestCase
{
    public function test_delete_job_tags(): void
    {
        $snapshot = QuerySnapshot::capture(User::where('is_blocked', true));
        $job = new DeleteRangeJob($snapshot, null, 'id');

        $this->assertSame(
            ['queue-sql', 'queue-sql:delete', 'queue-sql:delete:users'],
            $job->tags()
        );
    }

    public function test_update_job_tags(): void
    {
        $snapshot = QuerySnapshot::capture(User::where('is_blocked', true));
        $job = new UpdateRangeJob($snapshot, null, 'id', ['is_blocked' => false]);

        $this->assertSame(
            ['queue-sql', 'queue-sql:update', 'queue-sql:update:users'],
            $job->tags()
        );
    }

    public function test_insert_job_tags_from_query_builder_table(): void
    {
        $job = new InsertChunkJob(null, 'testing', 'users', [['name' => 'a']]);

        $this->assertSame(
            ['queue-sql', 'queue-sql:insert', 'queue-sql:insert:users'],
            $job->tags()
        );
    }

    public function test_insert_job_tags_from_eloquent_model(): void
    {
        $job = new InsertChunkJob(User::class, null, null, [['name' => 'a']]);

        $this->assertSame(
            ['queue-sql', 'queue-sql:insert', 'queue-sql:insert:users'],
            $job->tags()
        );
    }

    public function test_upsert_job_tags(): void
    {
        $job = new UpsertChunkJob(null, 'testing', 'users', [['id' => 1]], ['id'], ['name']);

        $this->assertSame(
            ['queue-sql', 'queue-sql:upsert', 'queue-sql:upsert:users'],
            $job->tags()
        );
    }
}
