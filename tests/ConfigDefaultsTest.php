<?php

namespace QueueSql\Tests;

use QueueSql\QueueConfig;
use QueueSql\Tests\Support\User;

class ConfigDefaultsTest extends TestCase
{
    public function test_queue_macro_flows_config_defaults_through(): void
    {
        // Guards the macro signature: if a param regains a non-null default in the
        // macro, config precedence silently breaks even though make() tests pass.
        config()->set('queue-sql.tries', 7);
        config()->set('queue-sql.backoff', 20);

        $config = User::query()->queue()->config();

        $this->assertSame(7, $config->tries);
        $this->assertSame(20, $config->backoff);
    }

    public function test_tries_falls_back_to_config(): void
    {
        config()->set('queue-sql.tries', 5);
        $this->assertSame(5, QueueConfig::make()->tries);
    }

    public function test_backoff_falls_back_to_config(): void
    {
        config()->set('queue-sql.backoff', 30);
        $this->assertSame(30, QueueConfig::make()->backoff);
    }

    public function test_throttle_falls_back_to_config(): void
    {
        config()->set('queue-sql.throttle', 10);
        $this->assertSame(10, QueueConfig::make()->throttle);
    }

    public function test_delay_falls_back_to_config(): void
    {
        config()->set('queue-sql.delay', 15);
        $this->assertSame(15, QueueConfig::make()->delay);
    }

    public function test_explicit_args_override_config_for_all_keys(): void
    {
        config()->set('queue-sql.tries', 5);
        config()->set('queue-sql.backoff', 30);
        config()->set('queue-sql.throttle', 10);
        config()->set('queue-sql.delay', 15);

        $c = QueueConfig::make(tries: 2, backoff: 4, throttle: 1, delay: 3);

        $this->assertSame(2, $c->tries);
        $this->assertSame(4, $c->backoff);
        $this->assertSame(1, $c->throttle);
        $this->assertSame(3, $c->delay);
    }

    public function test_unset_config_uses_builtin_fallback(): void
    {
        // No config set -> built-in defaults hold.
        $c = QueueConfig::make();
        $this->assertSame(1000, $c->chunk);
        $this->assertSame(1, $c->tries);
        $this->assertSame(0, $c->backoff);
        $this->assertNull($c->throttle);
        $this->assertNull($c->delay);
    }
}
