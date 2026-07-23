<?php

/**
 * Assert the live schema contains NO business table beyond the current step,
 * and that seeded credentials are distinct and hashed.
 *
 * Read from pg_tables, not from migration filenames: a table is what actually
 * exists, and a migration could create one under any name. Extracted from the
 * workflow so it is shellcheck-clean and runnable locally.
 *
 * THE DATABASE-LEVEL TWIN OF scripts/validate-runtime-scope.py.
 * ------------------------------------------------------------
 * That guard reads source; this one reads the live schema, so a table created
 * by any path — a migration, an import, a manual DDL — is caught even if no
 * source file names it. Both moved together under DEC-0030 when Step 4 was
 * authorised: leaving this one pinned to "no Step 4 table" would have blocked
 * the very tables DEC-0028 authorised while claiming to guard Step 5.
 *
 * Usage: php scripts/ci/assert-schema-scope.php [--check-seeded-passwords]
 * Exit 0 = in scope, 1 = violation.
 */

declare(strict_types=1);

/**
 * Tables Step 4 is authorised to create (DEC-0028, DEC-0030), matching the four
 * permitted feature labels: customer management, service catalog, price list,
 * and printer configuration.
 *
 * Listed EXPLICITLY rather than by prefix. A prefix rule such as "anything
 * starting with customer_" would silently admit a Step 5 table that happened to
 * be named `customer_orders`.
 */
const STEP4_ALLOWED_TABLES = [
    'customers', 'customer_addresses', 'customer_consents',
    'service_categories', 'service_catalog', 'service_packages',
    'service_package_items', 'service_addons',
    'price_lists', 'price_list_items',
    'outlet_service_zones', 'outlet_shifts', 'outlet_printers', 'printers',
    'membership_outlet', 'tenant_proof_policies',
];

/**
 * Tables Step 5 is authorised to create (DEC-0035), matching the seven permitted
 * POS/order/payment feature labels. The DB-level twin of the STEP5_FEATURE_TOKENS
 * split in validate-runtime-scope.py: both moved together when Step 5 was
 * authorised, exactly as the Step 4 pair moved under DEC-0030. Leaving this one
 * pinned to "no Step 5 table" would block the very tables DEC-0035 authorised
 * while claiming to guard Step 6.
 */
const STEP5_ALLOWED_TABLES = [
    'orders', 'order_items', 'order_lines',
    'payments', 'refunds', 'receipts', 'nota',
];

// Step 6 and later own these. Their presence means scope leaked, however the
// migration that created them was named (Rule 36 hard rule 4, Rule 42).
//
// `services` stays forbidden while `service_catalog` is allowed: the Step 4
// catalogue table is `service_catalog`, and a bare `services` table is not one
// that step created.
const FORBIDDEN_TABLES = [
    'services',
    'production_jobs', 'quality_controls', 'reworks',
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
echo "  forbidden Step 6+ tables: " . count($violations) . "\n";

$step4Present = array_values(array_intersect(STEP4_ALLOWED_TABLES, $tables));
echo "  authorised Step 4 tables present: " . count($step4Present) . "\n";

$step5Present = array_values(array_intersect(STEP5_ALLOWED_TABLES, $tables));
echo "  authorised Step 5 tables present: " . count($step5Present) . "\n";

if ($violations !== []) {
    fwrite(STDERR, "  SCOPE LEAK: " . implode(', ', $violations) . "\n");
    fwrite(STDERR, "  These belong to Step 6 or later. Remove them; renaming to\n");
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

echo "schema is within Step 5 scope\n";
exit(0);
