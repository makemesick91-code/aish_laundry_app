<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Authorization\PermissionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * THE DART ROLE CATALOGUE MUST MATCH THE SERVER REGISTRY.
 *
 * `packages/domain/lib/src/master_data/tenant_role.dart` enumerates the roles a
 * client may offer in a staff roster picker. It is maintained by hand — exactly
 * as `ApiErrorCode` mirrors `ErrorCode` — because a generated list hides drift
 * inside a build step, while a CHECKED list fails loudly in CI.
 *
 * This test is that check, and it runs on the SERVER side deliberately: the
 * registry is the authority, so the assertion belongs where the authority
 * lives. A Dart test could only compare the mirror against itself.
 *
 * WHAT DRIFT WOULD COST
 * ---------------------
 * A role added to the server but missing here means an operator cannot grant a
 * role that exists — an annoyance. A role present in Dart but NOT tenant-
 * assignable on the server is the dangerous direction: the picker would offer
 * it, the operator would choose it, and the request would be refused with a
 * validation error naming a role the interface had just presented as available.
 * Worse, a PLATFORM role appearing in the mirror would put platform
 * administration in a tenant operator's roster picker (DEC-0025 §8).
 *
 * The test reads the Dart source as TEXT rather than parsing it. The parse this
 * needs is exactly "which wire values are declared", a regular expression
 * answers it unambiguously, and a Dart toolchain is not available to a PHPUnit
 * process.
 */
final class TenantRoleCatalogueAlignmentTest extends TestCase
{
    private const DART_CATALOGUE = __DIR__
        .'/../../../packages/domain/lib/src/master_data/tenant_role.dart';

    public function test_the_dart_catalogue_file_exists_where_this_test_expects_it(): void
    {
        // Asserted separately so a MOVED file fails with "the mirror is not
        // where the check looks" rather than with an empty-set comparison that
        // would read as "the two lists agree".
        $this->assertFileExists(
            self::DART_CATALOGUE,
            'The Dart tenant-role catalogue has moved. Update this test to '
            .'follow it — a check that cannot find its subject verifies nothing.'
        );
    }

    public function test_the_dart_catalogue_enumerates_exactly_the_tenant_assignable_roles(): void
    {
        $serverRoles = $this->tenantAssignableRoleKeys();
        $dartRoles = $this->dartCatalogueWireValues();

        sort($serverRoles);
        sort($dartRoles);

        $this->assertSame(
            $serverRoles,
            $dartRoles,
            'The Dart tenant-role catalogue has drifted from PermissionRegistry. '
            ."Server: ".implode(', ', $serverRoles).'. '
            ."Dart: ".implode(', ', $dartRoles).'. '
            .'Update packages/domain/lib/src/master_data/tenant_role.dart.'
        );
    }

    public function test_the_dart_catalogue_contains_no_platform_role(): void
    {
        $dartRoles = $this->dartCatalogueWireValues();

        foreach (PermissionRegistry::platformRoleKeys() as $platformRole) {
            $this->assertNotContains(
                $platformRole,
                $dartRoles,
                sprintf(
                    'The platform role "%s" appears in the Dart catalogue. A '
                    .'platform role is never assignable through a membership '
                    .'(DEC-0025 §8), and a client that offered one would put '
                    .'platform administration in a tenant roster picker.',
                    $platformRole
                )
            );
        }
    }

    public function test_every_dart_role_is_actually_assignable_to_a_membership(): void
    {
        // The dangerous direction, asserted against the real guard rather than
        // against a second copy of the category list: a role the picker offers
        // must survive `assertAssignableToMembership`, or the interface is
        // presenting an option the server will refuse.
        foreach ($this->dartCatalogueWireValues() as $roleKey) {
            PermissionRegistry::assertAssignableToMembership($roleKey);
        }

        $this->addToAssertionCount(1);
    }

    /**
     * The role keys the server classifies as tenant-assignable.
     *
     * @return list<string>
     */
    private function tenantAssignableRoleKeys(): array
    {
        $keys = [];

        foreach (PermissionRegistry::roles() as $key => $definition) {
            if ($definition['category'] === PermissionRegistry::CATEGORY_TENANT) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The wire values declared by the Dart enum.
     *
     * Matches the first string literal of each enum entry, which is the
     * `wireValue` positional argument.
     *
     * @return list<string>
     */
    private function dartCatalogueWireValues(): array
    {
        $source = file_get_contents(self::DART_CATALOGUE);

        $this->assertIsString(
            $source,
            'The Dart tenant-role catalogue could not be read.'
        );

        // Only the enum BODY, so a wire value quoted in a doc comment further
        // down the file cannot be mistaken for a declaration.
        $start = strpos($source, 'enum TenantRole {');
        $this->assertNotFalse(
            $start,
            'The Dart catalogue no longer declares `enum TenantRole`. This '
            .'check parses that declaration; update it to follow the rename.'
        );

        $end = strpos($source, 'static TenantRole? parse(', $start);
        $this->assertNotFalse(
            $end,
            'The Dart catalogue no longer declares `parse`, which this check '
            .'uses to bound the enum body.'
        );

        $body = substr($source, $start, $end - $start);

        // `identifier('wire_value', ...` — the entry name, then its wire value.
        preg_match_all(
            "/^\s{2}[a-zA-Z][a-zA-Z0-9]*\(\s*\n?\s*'([a-z_]+)'/m",
            $body,
            $matches
        );

        $values = array_values(array_unique($matches[1]));

        $this->assertNotEmpty(
            $values,
            'No role wire values were parsed out of the Dart catalogue. The '
            .'declaration format has changed and this check is now blind — a '
            .'passing empty comparison would be worse than a failure.'
        );

        return $values;
    }
}
