<?php

/**
 * Assert the live schema contains NO Step 4+ business table, and that seeded
 * credentials are distinct and hashed.
 *
 * Read from pg_tables, not from migration filenames: a table is what actually
 * exists, and a migration could create one under any name. Extracted from the
 * workflow so it is shellcheck-clean and runnable locally.
 *
 * Usage: php scripts/ci/assert-schema-scope.php [--check-seeded-passwords]
 * Exit 0 = in scope, 1 = violation.
 */

declare(strict_types=1);

// Step 4 and later own these. Their presence means scope leaked, however the
// migration that created them was named (Rule 36 hard rule 4, Rule 42).
const FORBIDDEN_TABLES = [
    'customers', 'services', 'service_catalog', 'price_lists',
    'orders', 'order_items', 'order_lines', 'payments', 'refunds',
    'receipts', 'production_jobs', 'quality_controls', 'reworks',
    'tracking_tokens', 'deliveries', 'pickups', 'courier_routes',
    'delivery_proofs', 'reminders', 'reminder_stages', 'storage_fees',
    'receivables', 'finance_reports', 'loyalty', 'loyalty_points',
    'subscriptions', 'subscription_invoices',
];

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

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    env_or_fail('DB_HOST', '127.0.0.1'),
    env_or_fail('DB_PORT', '5432'),
    env_or_fail('DB_DATABASE'),
);

try {
    $pdo = new PDO($dsn, env_or_fail('DB_USERNAME'), env_or_fail('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "  could not connect: " . $e->getCode() . "\n");
    exit(1);
}

$tables = $pdo
    ->query("select tablename from pg_tables where schemaname = 'public' order by tablename")
    ->fetchAll(PDO::FETCH_COLUMN);

echo "  tables present: " . count($tables) . "\n";

$violations = array_values(array_intersect(FORBIDDEN_TABLES, $tables));
echo "  forbidden Step 4+ tables: " . count($violations) . "\n";

if ($violations !== []) {
    fwrite(STDERR, "  SCOPE LEAK: " . implode(', ', $violations) . "\n");
    fwrite(STDERR, "  These belong to Step 4 or later. Remove them; renaming to\n");
    fwrite(STDERR, "  evade detection is the same violation (Rule 36).\n");
    exit(1);
}

if (in_array('--check-seeded-passwords', $argv, true)) {
    echo "== seeded credentials ==\n";
    $hashes = $pdo->query('select password from users')->fetchAll(PDO::FETCH_COLUMN);
    $count = count($hashes);
    $distinct = count(array_unique($hashes));
    echo "  seeded users: {$count}, distinct password hashes: {$distinct}\n";

    // A shared default password across seeded accounts is the single most
    // commonly exploited development artefact.
    if ($count > 1 && $distinct < $count) {
        fwrite(STDERR, "  a shared default development password was seeded\n");
        exit(1);
    }
    foreach ($hashes as $h) {
        if (strlen((string) $h) < 40) {
            fwrite(STDERR, "  a password column does not hold a hash\n");
            exit(1);
        }
    }
    echo "  every seeded credential is distinct and hashed\n";
}

echo "schema is within Step 3 scope\n";
exit(0);
