<?php

/**
 * Assert PostgreSQL and Redis are GENUINELY reachable before any isolation
 * evidence is trusted.
 *
 * Extracted from the workflow. Inline `php -r '...'` inside a YAML block scalar
 * trips shellcheck SC2016 — PHP's `$var` looks like an unexpanded shell variable —
 * and, worse, could only be tested by pushing. As a file it is linted, testable,
 * and identical locally and in CI.
 *
 * A container reporting "started" is not a service accepting connections, so this
 * executes a real query and a real PING and FAILS CLOSED on anything unexpected.
 *
 * Usage: php scripts/ci/assert-services-reachable.php
 * Reads DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD/REDIS_HOST/REDIS_PORT
 * from the environment. Exit 0 = reachable, 1 = not.
 */

declare(strict_types=1);

function env_or_fail(string $key, ?string $default = null): string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        if ($default !== null) {
            return $default;
        }
        fwrite(STDERR, "  missing required environment variable: {$key}\n");
        exit(1);
    }

    return $v;
}

$dbHost = env_or_fail('DB_HOST', '127.0.0.1');
$dbPort = env_or_fail('DB_PORT', '5432');
$dbName = env_or_fail('DB_DATABASE');
$dbUser = env_or_fail('DB_USERNAME');
$dbPass = env_or_fail('DB_PASSWORD');
$redisHost = env_or_fail('REDIS_HOST', '127.0.0.1');
$redisPort = (int) env_or_fail('REDIS_PORT', '6379');

echo "== PostgreSQL ==\n";
try {
    // The password is read from the environment and never echoed. A connection
    // string in a log is a credential in a log.
    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    fwrite(STDERR, "  connection refused: " . $e->getCode() . "\n");
    exit(1);
}

$version = (string) $pdo->query('SHOW server_version')->fetchColumn();
echo "  server_version = {$version}\n";

// PostgreSQL is the AUTHORITATIVE engine for tenant-isolation evidence (Rule 43).
// A different major version is a different set of constraint semantics, so it is
// refused rather than accepted with a warning.
if (! str_starts_with($version, '18.')) {
    fwrite(STDERR, "  expected PostgreSQL 18.x, got {$version}\n");
    exit(1);
}

if ((int) $pdo->query('SELECT 1+1')->fetchColumn() !== 2) {
    fwrite(STDERR, "  query round-trip failed\n");
    exit(1);
}
echo "  query round-trip ok\n";

echo "== Redis ==\n";
if (! extension_loaded('redis')) {
    fwrite(STDERR, "  the redis extension is not loaded\n");
    exit(1);
}

$redis = new Redis();
if (! @$redis->connect($redisHost, $redisPort, 5.0)) {
    fwrite(STDERR, "  connection refused\n");
    exit(1);
}
if ($redis->ping() === false) {
    fwrite(STDERR, "  PING failed\n");
    exit(1);
}

$info = $redis->info();
$redisVersion = $info['redis_version'] ?? 'unknown';
echo "  PING ok, redis_version = {$redisVersion}\n";

// Prove a real write/read cycle rather than trusting PING alone, then clean up.
$key = 'aish:ci:reachability:' . bin2hex(random_bytes(6));
$redis->set($key, 'ok');
if ($redis->get($key) !== 'ok') {
    fwrite(STDERR, "  write/read cycle failed\n");
    exit(1);
}
$redis->del($key);
echo "  write/read/delete cycle ok\n";

echo "both services verified by executed command\n";
exit(0);
