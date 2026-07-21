import 'package:meta/meta.dart';

/// The role keys that may be granted through a TENANT MEMBERSHIP.
///
/// WHY THIS LIST EXISTS AT ALL
/// ---------------------------
/// A roster screen has to offer a choice, and a free-text role field is how a
/// client ends up POSTing a role key nobody defined. This enumerates exactly
/// what the server's `PermissionRegistry` classifies as `CATEGORY_TENANT`, so
/// the picker cannot offer anything else.
///
/// WHAT IT IS NOT
/// --------------
/// It is NOT an authorization decision and it is NOT the escalation guard.
/// Offering a role here says only "this key exists". Whether THIS caller may
/// grant it to THAT membership is decided server-side by
/// `StaffAssignmentRegistry::assertNoEscalation`, which refuses any role
/// carrying a permission the caller does not itself hold. A client that showed
/// too many options produces a refused request, never an unauthorized grant
/// (Rule 40 hard rules 1–2).
///
/// PLATFORM ROLES ARE ABSENT, AND THAT ABSENCE IS TESTED.
/// `platform_super_admin` and `platform_support` are `CATEGORY_PLATFORM` and are
/// never assignable through a membership (DEC-0025 §8). They have no member
/// here, so a tenant operator's roster picker cannot render one even by mistake.
///
/// ALIGNMENT WITH THE SERVER IS ASSERTED, NOT ASSUMED.
/// The two lists are maintained by hand and cross-checked by
/// `backend/tests/.../TenantRoleCatalogueAlignmentTest.php`, which reads THIS
/// file and fails if the sets diverge. A generated list would hide the drift
/// inside a build step; a checked list fails loudly in CI.
enum TenantRole {
  tenantOwner('tenant_owner', 'Pemilik tenant', isStaffRole: true),
  tenantAdmin('tenant_admin', 'Admin tenant', isStaffRole: true),
  outletManager('outlet_manager', 'Manager outlet', isStaffRole: true),
  cashier('cashier', 'Kasir', isStaffRole: true),
  productionOperator(
    'production_operator',
    'Operator produksi',
    isStaffRole: true,
  ),
  qualityControl('quality_control', 'Quality control', isStaffRole: true),
  courier('courier', 'Kurir', isStaffRole: true),
  finance('finance', 'Finance', isStaffRole: true),

  /// A tenant-category role, but NOT a staff role.
  ///
  /// It exists so a customer can reach their own data. It is enumerated here
  /// because the server does classify it as tenant-assignable and this mirror
  /// must stay truthful about that — but [isStaffRole] is false, so the staff
  /// roster does not offer it. Hiding it from the mirror entirely would make the
  /// alignment test fail for a reason that is not a defect.
  customer('customer', 'Pelanggan', isStaffRole: false);

  const TenantRole(this.wireValue, this.label, {required this.isStaffRole});

  /// The exact key on the wire. Never translated, never abbreviated.
  final String wireValue;

  /// The Bahasa Indonesia label shown to an operator.
  final String label;

  /// Whether this role belongs in a STAFF roster picker.
  final bool isStaffRole;

  /// Parse a wire value, returning `null` when this build does not know it.
  ///
  /// Null rather than a fallback member, for the same reason `ApiErrorCode`
  /// does it: a client that coerced an unknown role into a known one would
  /// display the wrong capability against a real person's name.
  static TenantRole? parse(String? value) {
    for (final role in TenantRole.values) {
      if (role.wireValue == value) {
        return role;
      }
    }
    return null;
  }

  /// The roles a staff roster may offer.
  static List<TenantRole> get assignableToStaff => TenantRole.values
      .where((role) => role.isStaffRole)
      .toList(growable: false);

  /// Render an unrecognised key without pretending to understand it.
  static String labelFor(String wireValue) =>
      parse(wireValue)?.label ?? wireValue;
}

/// A role as it appears against one membership on the roster.
@immutable
final class AssignedRole {
  const AssignedRole({required this.wireValue, this.role});

  factory AssignedRole.fromWire(String wireValue) =>
      AssignedRole(wireValue: wireValue, role: TenantRole.parse(wireValue));

  /// Exactly what the server said, preserved so a revocation sends back the
  /// same key rather than a re-derived one.
  final String wireValue;

  /// Null when this build does not recognise the key.
  final TenantRole? role;

  String get label => role?.label ?? wireValue;

  /// Whether this build understands the key well enough to offer a revoke
  /// control for it. An unknown key is displayed and left alone.
  bool get isRecognised => role != null;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is AssignedRole && other.wireValue == wireValue);

  @override
  int get hashCode => wireValue.hashCode;

  @override
  String toString() => 'AssignedRole($wireValue)';
}
