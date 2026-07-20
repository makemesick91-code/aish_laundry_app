import 'package:aish_domain/aish_domain.dart';
import 'package:test/test.dart';

const Tenant melati = Tenant(
  id: 'tnt_a',
  name: 'Laundry Melati (fiktif)',
  isActive: true,
);
const Tenant kenanga = Tenant(
  id: 'tnt_b',
  name: 'Laundry Kenanga (fiktif)',
  isActive: true,
);
const User user = User(id: 'usr_1', displayName: 'Bu Rina (fiktif)');
const Membership activeMembership = Membership(
  id: 'mbr_1',
  userId: 'usr_1',
  tenantId: 'tnt_a',
  status: MembershipStatus.active,
  roles: <Role>[Role(slug: Role.tenantOwner, label: 'Pemilik')],
);

EffectivePermissions permissionsFor(String tenantId) => EffectivePermissions(
  tenantId: tenantId,
  permissions: <Permission>{const Permission(Permission.outletView)},
);

void main() {
  group('MembershipStatus.parse fails safe', () {
    test('parses known statuses', () {
      expect(MembershipStatus.parse('active'), MembershipStatus.active);
      expect(MembershipStatus.parse('revoked'), MembershipStatus.revoked);
    });

    test('an UNKNOWN status becomes suspended, never active', () {
      // If the server grows a status this build does not know, the client
      // withholds access. The opposite default would turn an unknown string
      // into a grant.
      final parsed = MembershipStatus.parse('some_new_status');
      expect(parsed, MembershipStatus.suspended);
      expect(parsed, isNot(MembershipStatus.active));
    });
  });

  group('EffectivePermissions', () {
    test('grants nothing by default', () {
      const none = EffectivePermissions.none(tenantId: 'tnt_a');
      expect(none.isEmpty, isTrue);
      expect(
        none.allows(Permission.outletView, expectedTenantId: 'tnt_a'),
        isFalse,
      );
    });

    test('answers within its own tenant', () {
      final permissions = permissionsFor('tnt_a');
      expect(
        permissions.allows(Permission.outletView, expectedTenantId: 'tnt_a'),
        isTrue,
      );
      expect(
        permissions.allows(Permission.auditView, expectedTenantId: 'tnt_a'),
        isFalse,
      );
    });

    test('THROWS when consulted across a tenant boundary', () {
      // A silent `false` would look like a denial and hide the fact that the
      // wrong context was consulted. This must be loud.
      final permissions = permissionsFor('tnt_a');
      expect(
        () => permissions.allows(
          Permission.outletView,
          expectedTenantId: 'tnt_b',
        ),
        throwsA(isA<StateError>()),
      );
    });

    test('its permission set cannot be mutated after construction', () {
      final permissions = permissionsFor('tnt_a');
      expect(
        () => permissions.permissions.add(const Permission('anything')),
        throwsUnsupportedError,
      );
    });
  });

  group('SessionState', () {
    test('requires explicit tenant selection even with one tenant', () {
      const session = SessionState(
        user: user,
        availableTenants: <Tenant>[melati],
      );
      expect(session.requiresTenantSelection, isTrue);
      expect(session.hasTenantContext, isFalse);
      expect(session.needsTenantSwitcher, isFalse);
    });

    test('needs a switcher when the user belongs to more than one tenant', () {
      const session = SessionState(
        user: user,
        availableTenants: <Tenant>[melati, kenanga],
      );
      expect(session.needsTenantSwitcher, isTrue);
    });

    test('grants nothing before a tenant is selected', () {
      const session = SessionState(
        user: user,
        availableTenants: <Tenant>[melati],
      );
      expect(session.allows(Permission.outletView), isFalse);
    });

    test('a suspended membership yields no tenant context', () {
      final session = SessionState(
        user: user,
        availableTenants: const <Tenant>[melati],
        activeTenant: melati,
        activeMembership: const Membership(
          id: 'mbr_1',
          userId: 'usr_1',
          tenantId: 'tnt_a',
          status: MembershipStatus.suspended,
          roles: <Role>[],
        ),
        permissions: permissionsFor('tnt_a'),
      );
      expect(session.hasTenantContext, isFalse);
      expect(session.allows(Permission.outletView), isFalse);
    });

    test('withoutTenantContext drops EVERY tenant-scoped element', () {
      final full = SessionState(
        user: user,
        availableTenants: const <Tenant>[melati, kenanga],
        activeTenant: melati,
        activeMembership: activeMembership,
        activeOutlet: const Outlet(
          id: 'otl_1',
          tenantId: 'tnt_a',
          brandId: 'brd_1',
          name: 'Outlet (fiktif)',
          isActive: true,
        ),
        permissions: permissionsFor('tnt_a'),
      );

      final cleared = full.withoutTenantContext();

      // Nothing from the previous tenant may survive a switch — this is the
      // leak Rule 28 rule 3 exists to prevent.
      expect(cleared.activeTenant, isNull);
      expect(cleared.activeOutlet, isNull);
      expect(cleared.activeMembership, isNull);
      expect(cleared.permissions, isNull);
      expect(cleared.allows(Permission.outletView), isFalse);
      // Identity survives; the user did not change.
      expect(cleared.user, full.user);
      expect(cleared.availableTenants, full.availableTenants);
    });
  });

  group('Outlet and brand carry tenant ownership', () {
    test('every tenant-scoped projection names its tenant', () {
      const outlet = Outlet(
        id: 'otl_1',
        tenantId: 'tnt_a',
        brandId: 'brd_1',
        name: 'Outlet (fiktif)',
        isActive: true,
      );
      const brand = LaundryBrand(
        id: 'brd_1',
        tenantId: 'tnt_a',
        name: 'Brand (fiktif)',
        isActive: true,
      );
      expect(outlet.tenantId, isNotEmpty);
      expect(brand.tenantId, isNotEmpty);
    });
  });
}
