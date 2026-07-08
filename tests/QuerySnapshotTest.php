<?php

namespace QueueSql\Tests;

use QueueSql\QuerySnapshot;
use QueueSql\Tests\Support\User;

class QuerySnapshotTest extends TestCase
{
    private function seedRows(): void
    {
        User::insert([
            ['name' => 'keep-me', 'is_blocked' => false],
            ['name' => 'a', 'is_blocked' => true],
            ['name' => 'b', 'is_blocked' => true],
        ]);
    }

    /** Compare by result set, since restore() uses whereRaw (SQL text differs by design). */
    private function ids($builder): array
    {
        return $builder->orderBy('id')->pluck('id')->all();
    }

    public function test_flat_where_selects_same_rows(): void
    {
        $this->seedRows();
        $original = User::where('is_blocked', true);
        $snapshot = QuerySnapshot::capture($original);

        $this->assertSame($this->ids($original), $this->ids($snapshot->restore()));
        $this->assertSame('id', $snapshot->keyName());
        $this->assertTrue($snapshot->canFanOut());
    }

    public function test_snapshot_survives_serialization(): void
    {
        $this->seedRows();
        $snapshot = QuerySnapshot::capture(User::where('is_blocked', true));
        $revived = unserialize(serialize($snapshot));

        $this->assertSame(
            $this->ids(User::where('is_blocked', true)),
            $this->ids($revived->restore())
        );
    }

    public function test_nested_closure_where_is_supported(): void
    {
        $this->seedRows();
        $original = User::where(function ($q) {
            $q->where('is_blocked', true)->orWhere('name', 'keep-me');
        });
        $snapshot = QuerySnapshot::capture($original);

        // All three rows match (2 blocked + the 'keep-me' row).
        $this->assertSame([1, 2, 3], $this->ids($snapshot->restore()));
        $this->assertSame($this->ids($original), $this->ids($snapshot->restore()));
    }

    public function test_no_where_restores_unconstrained(): void
    {
        $this->seedRows();
        $snapshot = QuerySnapshot::capture(User::query());
        $this->assertSame([1, 2, 3], $this->ids($snapshot->restore()));
    }
}
