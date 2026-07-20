<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Operational endpoints for the Step 3 runtime foundation.
 *
 * Honesty contract (Rule 01): these endpoints report only what they actually
 * executed. `health` asserts that the PHP process booted the framework — and
 * nothing more. `readiness` asserts nothing it did not prove: it performs a
 * real round-trip against PostgreSQL and against Redis and reports the outcome
 * of each, failing closed with HTTP 503 when either dependency is unreachable.
 *
 * Neither endpoint is evidence that any product feature exists. All product
 * features remain NOT IMPLEMENTED.
 */
class HealthController extends Controller
{
    /**
     * Liveness: the process is up and the framework booted.
     *
     * Deliberately checks no dependency. A liveness probe that fails when a
     * downstream dependency is unavailable causes restart loops that make an
     * outage worse rather than better.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'checked' => 'process and framework boot only',
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness: every dependency this runtime cannot serve traffic without.
     *
     * Returns HTTP 200 only when PostgreSQL and Redis both answered. Any
     * failure yields HTTP 503 and names the dependency that failed.
     */
    public function readiness(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $ready = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    /**
     * Executed round-trip against PostgreSQL. Never reports a connection it
     * did not actually open.
     *
     * @return array{ok: bool, driver: string, detail: string}
     */
    private function checkDatabase(): array
    {
        $started = microtime(true);

        try {
            $value = DB::selectOne('select 1 as ready');

            if (! $value || (int) $value->ready !== 1) {
                return [
                    'ok' => false,
                    'driver' => (string) config('database.default'),
                    'detail' => 'query executed but returned an unexpected result',
                ];
            }

            return [
                'ok' => true,
                'driver' => (string) config('database.default'),
                'detail' => sprintf('select 1 round-trip in %.1f ms', (microtime(true) - $started) * 1000),
            ];
        } catch (Throwable $e) {
            // The message is a connection diagnostic, not a credential. Config
            // values are never echoed here.
            return [
                'ok' => false,
                'driver' => (string) config('database.default'),
                'detail' => 'connection failed: '.class_basename($e),
            ];
        }
    }

    /**
     * Executed round-trip against Redis.
     *
     * @return array{ok: bool, client: string, detail: string}
     */
    private function checkRedis(): array
    {
        $started = microtime(true);

        try {
            $pong = Redis::connection()->ping();
            $answered = is_string($pong)
                ? strtoupper($pong) === 'PONG' || $pong === '+PONG'
                : (bool) $pong;

            if (! $answered) {
                return [
                    'ok' => false,
                    'client' => (string) config('database.redis.client'),
                    'detail' => 'PING did not answer PONG',
                ];
            }

            return [
                'ok' => true,
                'client' => (string) config('database.redis.client'),
                'detail' => sprintf('PING round-trip in %.1f ms', (microtime(true) - $started) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'client' => (string) config('database.redis.client'),
                'detail' => 'connection failed: '.class_basename($e),
            ];
        }
    }
}
