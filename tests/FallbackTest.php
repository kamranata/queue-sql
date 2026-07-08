<?php

namespace QueueSql\Tests;

use Illuminate\Support\Facades\Bus;
use QueueSql\Tests\Support\Token;

class FallbackTest extends TestCase
{
    public function test_non_orderable_key_produces_single_job(): void
    {
        Token::insert([
            ['uuid' => 'a', 'revoked' => true],
            ['uuid' => 'b', 'revoked' => true],
            ['uuid' => 'c', 'revoked' => false],
        ]);
        Bus::fake();

        Token::where('revoked', true)->queue(chunk: 1)->delete()->dispatch();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    public function test_fallback_delete_runs_correctly(): void
    {
        Token::insert([
            ['uuid' => 'a', 'revoked' => true],
            ['uuid' => 'b', 'revoked' => true],
            ['uuid' => 'c', 'revoked' => false],
        ]);
        config()->set('queue.default', 'sync');

        Token::where('revoked', true)->queue()->delete()->dispatch();

        $this->assertSame(1, Token::count());
    }
}
