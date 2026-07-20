<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Covers the Step 3 operational endpoints only.
 *
 * These tests prove that the runtime boots and that readiness reports what it
 * actually executed, including that it FAILS CLOSED. They prove nothing about
 * any product feature — all product features remain NOT IMPLEMENTED.
 */
class OperationalEndpointsTest extends TestCase
{
    public function test_health_reports_liveness_without_checking_dependencies(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_readiness_reports_ready_when_postgres_and_redis_answer(): void
    {
        $this->getJson('/api/v1/readiness')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.redis.ok', true);
    }

    /**
     * The negative path. A readiness probe that cannot fail is not a probe.
     */
    public function test_readiness_fails_closed_when_a_dependency_is_unreachable(): void
    {
        config([
            'database.connections.pgsql.port' => 1,
            'database.redis.default.port' => 2,
        ]);
        $this->app->make('db')->purge('pgsql');

        $response = $this->getJson('/api/v1/readiness')
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.database.ok', false)
            ->assertJsonPath('checks.redis.ok', false);

        // A failure names the dependency, never the credential or the host.
        $body = $response->getContent();
        $this->assertStringNotContainsString((string) config('database.connections.pgsql.password'), $body);
        $this->assertStringNotContainsString((string) config('database.connections.pgsql.host'), $body);
        $this->assertStringNotContainsString((string) config('database.connections.pgsql.username'), $body);
    }
}
