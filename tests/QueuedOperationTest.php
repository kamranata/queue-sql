<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use QueueSql\QueuedOperation;
use QueueSql\Tests\Support\User;

class QueuedOperationTest extends TestCase
{
    private function seedRows(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['name' => "u{$i}", 'is_blocked' => true];
        }
        User::insert($rows);
    }

    public function test_delete_returns_operation_and_does_not_dispatch_until_called(): void
    {
        $this->seedRows(8);
        Bus::fake();

        $op = User::where('is_blocked', true)->queue(chunk: 4)->delete();

        $this->assertInstanceOf(QueuedOperation::class, $op);
        Bus::assertNothingBatched();

        $op->dispatch();
        Bus::assertBatched(fn ($b) => $b->jobs->count() === 2);
    }

    public function test_dry_run_reports_plan_without_dispatching(): void
    {
        $this->seedRows(8);
        Bus::fake();

        $plan = User::where('is_blocked', true)->queue(chunk: 4)->delete()->dryRun();

        $this->assertSame('delete', $plan['operation']);
        $this->assertSame(2, $plan['jobs']);
        $this->assertSame(8, $plan['estimatedRows']);
        Bus::assertNothingBatched();
    }
}
