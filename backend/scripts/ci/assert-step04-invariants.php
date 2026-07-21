<?php

/**
 * Assert Step 4's two structural hard gates against the LIVE schema.
 *
 *   1. NO FLOATING-POINT MONEY COLUMN (Rule 04 hard rule 2).
 *   2. EVERY Step 4 business table carries `tenant_id` (Rule 02 hard rule 7).
 *
 * READ FROM information_schema, NOT FROM MIGRATION SOURCE.
 * A migration could be edited, superseded, or bypassed by a manual DDL. What
 * exists in the database is what a query will actually read, so that is what is
 * checked — the same discipline `assert-schema-scope.php` applies to table
 * presence, applied here to column types and tenant scoping.
 *
 * WHY THIS MATTERS BEYOND STEP 4
 * ------------------------------
 * A `float` money column introduced here would be INHERITED by Step 5's
 * payments (Rule 42), and a business table without `tenant_id` is a
 * tenant-isolation defect rather than a modelling nitpick (Rule 02). Both are
 * cheapest to catch now and most expensive to catch after an order references
 * the column.
 *
 * Usage: php scripts/ci/assert-step04-invariants.php
 * Exit 0 = both invariants hold, 1 = violation, 2 = could not run.
 *
 * A run that COULD NOT CONNECT exits 2 and is never reported as a pass: a gate
 * that did not execute has verified nothing (Rule 01).
 */

declare(strict_types=1);

/**
 * Business tables Step 4 introduces or extends, every one of which must be
 * tenant-scoped.
 *
 * Listed explicitly rather than discovered by prefix. A prefix rule would
 * silently excuse a table nobody listed, which is precisely the table most
 * likely to have been added without thinking about tenancy.
 */
const STEP4_BUSINESS_TABLES = [
    'customers',
    'customer_addresses',
    'customer_consents',
    'service_categories',
    'services',
    'service_packages',
    'service_package_items',
    'service_addons',
    'price_lists',
    'price_list_items',
    'outlet_service_zones',
    'outlet_shifts',
    'outlet_printers',
    'membership_outlet',
    'tenant_proof_policies',
];

/**
 * Column-name fragments that indicate a MONEY column.
 *
 * Money is integer Rupiah (Rule 04 hard rule 1). Any column whose name says it
 * holds an amount must not be a floating-point or arbitrary-precision decimal
 * type — `numeric` is included because a decimal money column invites decimal
 * arithmetic, and the canonical representation is an integer count of Rupiah.
 */
const MONEY_NAME_FRAGMENTS = [
    'amount', 'price', 'total', 'rupiah', 'cash', 'balance', 'fee', 'cost',
    'subtotal', 'discount', 'tax',
];

const INEXACT_TYPES = ['real', 'double precision', 'numeric', 'decimal', 'money'];

function env_or_fail(string $key): string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        fwrite(STDERR, "  environment variable {$key} is not set\n");
        exit(2);
    }

    return $value;
}

try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        env_or_fail('DB_HOST'),
        env_or_fail('DB_PORT'),
        env_or_fail('DB_DATABASE')
    );

    $pdo = new PDO($dsn, env_or_fail('DB_USERNAME'), env_or_fail('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, '  could not connect: ' . $e->getCode() . "\n");
    exit(2);
}

echo "========================================================================\n";
echo "STEP 4 SCHEMA INVARIANTS — read from the live database\n";
echo "========================================================================\n\n";

$failures = [];

// ---------------------------------------------------------------------------
// 1. No floating-point money column.
// ---------------------------------------------------------------------------
$columns = $pdo
    ->query(
        "SELECT table_name, column_name, data_type
           FROM information_schema.columns
          WHERE table_schema = 'public'"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

$moneyColumns = 0;

foreach ($columns as $column) {
    $looksLikeMoney = false;

    foreach (MONEY_NAME_FRAGMENTS as $fragment) {
        if (str_contains($column['column_name'], $fragment)) {
            $looksLikeMoney = true;
            break;
        }
    }

    if (! $looksLikeMoney) {
        continue;
    }

    $moneyColumns++;

    if (in_array($column['data_type'], INEXACT_TYPES, true)) {
        $failures[] = sprintf(
            'FLOATING-POINT MONEY: %s.%s is %s. Money is integer Rupiah '
            .'(Rule 04 hard rule 2), and this column would be inherited by '
            ."Step 5's payment paths.",
            $column['table_name'],
            $column['column_name'],
            $column['data_type']
        );
    }
}

printf(
    "%s  no money column uses an inexact numeric type (%d money columns examined)\n",
    $failures === [] ? 'PASS' : 'FAIL',
    $moneyColumns
);

$moneyFailureCount = count($failures);

// ---------------------------------------------------------------------------
// 2. Every Step 4 business table carries tenant_id.
// ---------------------------------------------------------------------------
$present = 0;
$absent = [];

foreach (STEP4_BUSINESS_TABLES as $table) {
    $exists = $pdo
        ->query(
            "SELECT 1 FROM information_schema.tables
              WHERE table_schema = 'public' AND table_name = " . $pdo->quote($table)
        )
        ->fetchColumn();

    if (! $exists) {
        // A table that does not exist is NOT a failure here: this list spans
        // names the schema may legitimately not use. Its absence is reported so
        // a reader can see what was and was not actually checked, rather than
        // reading a clean pass as coverage it did not have.
        continue;
    }

    $present++;

    $hasTenant = $pdo
        ->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = 'public'
                AND table_name = " . $pdo->quote($table) . "
                AND column_name = 'tenant_id'"
        )
        ->fetchColumn();

    if (! $hasTenant) {
        $absent[] = $table;
        $failures[] = sprintf(
            'NO tenant_id: the business table "%s" exists without a tenant_id '
            .'column. Every business table carries tenant_id from its '
            .'introducing migration (Rule 02 hard rule 7); this is a '
            .'tenant-isolation defect, not a modelling nitpick.',
            $table
        );
    }
}

printf(
    "%s  every present Step 4 business table carries tenant_id (%d of %d listed tables exist)\n",
    $absent === [] ? 'PASS' : 'FAIL',
    $present,
    count(STEP4_BUSINESS_TABLES)
);

echo "\n";

if ($failures !== []) {
    echo "FAILURES:\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
    echo "\n";
    printf(
        "SUMMARY [step-04-invariants]: %d money, %d tenant_id failure(s)\n",
        $moneyFailureCount,
        count($failures) - $moneyFailureCount
    );
    echo "RESULT: FAIL (step-04-invariants)\n";
    exit(1);
}

echo "SUMMARY [step-04-invariants]: 2/2 checks passed, 0 failed\n";
echo "RESULT: PASS (step-04-invariants)\n";
exit(0);
