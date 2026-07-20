import 'package:aish_domain/aish_domain.dart';

/// Fictional fixtures for the Step 3 identity and tenancy surface.
///
/// Every value is invented. The two tenants are named so that a reader can see
/// at a glance that a cross-tenant assertion is testing genuinely unrelated
/// businesses rather than two rows that happen to differ.
///
/// No phone number, address, email, or token appears here. Where a masked phone
/// is needed, it is a mask of a number that does not exist.
abstract final class ApiFixtures {
  // -- identities ---------------------------------------------------------
  static const User owner = User(
    id: 'usr_fiktif_0001',
    displayName: 'Bu Rina (fiktif)',
    maskedPhone: '+62••••••1234',
  );

  static const User cashier = User(
    id: 'usr_fiktif_0002',
    displayName: 'Mas Adi (fiktif)',
    maskedPhone: '+62••••••5678',
  );

  // -- two unrelated tenants ----------------------------------------------
  static const Tenant tenantMelati = Tenant(
    id: 'tnt_fiktif_melati',
    name: 'Laundry Melati (fiktif)',
    isActive: true,
  );

  static const Tenant tenantKenanga = Tenant(
    id: 'tnt_fiktif_kenanga',
    name: 'Laundry Kenanga (fiktif)',
    isActive: true,
  );

  static const Tenant tenantInactive = Tenant(
    id: 'tnt_fiktif_nonaktif',
    name: 'Laundry Nonaktif (fiktif)',
    isActive: false,
  );

  // -- brands and outlets --------------------------------------------------
  static const LaundryBrand brandMelati = LaundryBrand(
    id: 'brd_fiktif_melati',
    tenantId: 'tnt_fiktif_melati',
    name: 'Melati Express (fiktif)',
    isActive: true,
  );

  static const Outlet outletMelatiPusat = Outlet(
    id: 'otl_fiktif_melati_pusat',
    tenantId: 'tnt_fiktif_melati',
    brandId: 'brd_fiktif_melati',
    name: 'Outlet Pusat (fiktif)',
    isActive: true,
  );

  static const Outlet outletMelatiCabang = Outlet(
    id: 'otl_fiktif_melati_cabang',
    tenantId: 'tnt_fiktif_melati',
    brandId: 'brd_fiktif_melati',
    name: 'Outlet Cabang (fiktif)',
    isActive: true,
  );

  static const Outlet outletMelatiTutup = Outlet(
    id: 'otl_fiktif_melati_tutup',
    tenantId: 'tnt_fiktif_melati',
    brandId: 'brd_fiktif_melati',
    name: 'Outlet Tutup (fiktif)',
    isActive: false,
  );

  /// An outlet in the OTHER tenant. Used by isolation tests to prove it is
  /// never reachable from a Melati context.
  static const Outlet outletKenanga = Outlet(
    id: 'otl_fiktif_kenanga_pusat',
    tenantId: 'tnt_fiktif_kenanga',
    brandId: 'brd_fiktif_kenanga',
    name: 'Kenanga Pusat (fiktif)',
    isActive: true,
  );

  // -- roles and memberships ----------------------------------------------
  static const Role roleOwner = Role(
    slug: Role.tenantOwner,
    label: 'Pemilik Tenant',
  );
  static const Role roleCashier = Role(slug: Role.cashier, label: 'Kasir');
  static const Role roleCourier = Role(slug: Role.courier, label: 'Kurir');

  static const Membership membershipOwnerMelati = Membership(
    id: 'mbr_fiktif_0001',
    userId: 'usr_fiktif_0001',
    tenantId: 'tnt_fiktif_melati',
    status: MembershipStatus.active,
    roles: <Role>[roleOwner],
  );

  static const Membership membershipCashierMelati = Membership(
    id: 'mbr_fiktif_0002',
    userId: 'usr_fiktif_0002',
    tenantId: 'tnt_fiktif_melati',
    status: MembershipStatus.active,
    roles: <Role>[roleCashier],
    defaultOutletId: 'otl_fiktif_melati_pusat',
  );

  static const Membership membershipSuspended = Membership(
    id: 'mbr_fiktif_0003',
    userId: 'usr_fiktif_0002',
    tenantId: 'tnt_fiktif_kenanga',
    status: MembershipStatus.suspended,
    roles: <Role>[roleCourier],
  );

  // -- permissions ---------------------------------------------------------
  static EffectivePermissions ownerPermissions(String tenantId) =>
      EffectivePermissions(
        tenantId: tenantId,
        permissions: <Permission>{
          Permission(Permission.tenantView),
          Permission(Permission.tenantSwitch),
          Permission(Permission.brandView),
          Permission(Permission.outletView),
          Permission(Permission.outletSwitch),
          Permission(Permission.membershipView),
          Permission(Permission.sessionViewSelf),
          Permission(Permission.sessionRevokeSelf),
          Permission(Permission.deviceSessionView),
          Permission(Permission.permissionInspect),
          Permission(Permission.auditView),
        },
      );

  static EffectivePermissions cashierPermissions(String tenantId) =>
      EffectivePermissions(
        tenantId: tenantId,
        permissions: <Permission>{
          Permission(Permission.tenantView),
          Permission(Permission.outletView),
          Permission(Permission.sessionViewSelf),
        },
      );

  // -- session states ------------------------------------------------------
  /// Authenticated, but no tenant chosen yet.
  static SessionState signedInNoTenant({List<Tenant>? tenants}) => SessionState(
    user: owner,
    availableTenants: tenants ?? const <Tenant>[tenantMelati, tenantKenanga],
  );

  /// Authenticated with a resolved tenant and outlet.
  static SessionState fullContext() => SessionState(
    user: owner,
    availableTenants: const <Tenant>[tenantMelati, tenantKenanga],
    activeTenant: tenantMelati,
    activeMembership: membershipOwnerMelati,
    activeOutlet: outletMelatiPusat,
    permissions: ownerPermissions(tenantMelati.id),
  );

  /// Authenticated with a tenant but no outlet chosen.
  static SessionState tenantOnly() => SessionState(
    user: owner,
    availableTenants: const <Tenant>[tenantMelati, tenantKenanga],
    activeTenant: tenantMelati,
    activeMembership: membershipOwnerMelati,
    permissions: ownerPermissions(tenantMelati.id),
  );

  // -- raw envelopes -------------------------------------------------------
  /// A success envelope in the exact shape the backend emits.
  static Map<String, Object?> successEnvelope(Object? data) =>
      <String, Object?>{
        'data': data,
        'meta': <String, Object?>{'request_id': 'req_fiktif_00000000'},
      };

  /// An error envelope in the exact shape the backend emits.
  static Map<String, Object?> errorEnvelope(
    String code, {
    String message = 'Pesan galat fiktif.',
    Map<String, Object?>? details,
  }) => <String, Object?>{
    'error': <String, Object?>{
      'code': code,
      'message': message,
      // Omitted entirely when absent, exactly as the backend does — a
      // client must never have to tell "missing" from "null".
      'details': ?details,
    },
    'meta': <String, Object?>{'request_id': 'req_fiktif_00000000'},
  };
}
