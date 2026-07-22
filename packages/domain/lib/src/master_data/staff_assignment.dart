import 'package:meta/meta.dart';

/// A member of the tenant, as a roster surface sees them
/// (ROADMAP Step 4 scope, FR-018).
///
/// NO PHONE NUMBER FIELD. A roster has no operational need for one, and the
/// server's projection does not send it. Modelling it here would create a place
/// for one to arrive if the server projection ever widened — the narrowest type
/// that does the job cannot leak the rest (Rule 32 hard rule 4).
///
/// [roles] ARE FOR DISPLAY. Authorization decisions are made on PERMISSIONS,
/// server-side, on every request. A surface that branched on a role name would
/// silently grant or remove access the moment a role was renamed, and hiding a
/// control is never the access control anyway (Rule 40 hard rule 2).
@immutable
final class StaffMember {
  const StaffMember({
    required this.membershipId,
    required this.status,
    required this.userName,
    this.userId,
    this.userEmail,
    this.roles = const <String>[],
    this.outletAssignments = const <OutletAssignment>[],
  });

  factory StaffMember.fromJson(Map<String, Object?> json) {
    final user =
        (json['user'] as Map<String, Object?>?) ?? const <String, Object?>{};

    return StaffMember(
      membershipId: json['membership_id']! as String,
      status: json['status'] as String? ?? 'invited',
      userName: user['name'] as String? ?? '',
      userId: user['id'] as String?,
      userEmail: user['email'] as String?,
      roles: ((json['roles'] as List<Object?>?) ?? const <Object?>[])
          .cast<String>()
          .toList(growable: false),
      outletAssignments:
          ((json['outlet_assignments'] as List<Object?>?) ?? const <Object?>[])
              .cast<Map<String, Object?>>()
              .map(OutletAssignment.fromJson)
              .toList(growable: false),
    );
  }

  final String membershipId;

  /// `invited`, `active`, `suspended`, or `revoked`. Only `active` grants
  /// access; the others each produce a distinct server error so a user is told
  /// what actually happened rather than left to guess.
  final String status;

  final String userName;
  final String? userId;
  final String? userEmail;

  /// Display only. See the class comment.
  final List<String> roles;

  final List<OutletAssignment> outletAssignments;

  bool get isActive => status == 'active';

  List<OutletAssignment> get liveAssignments =>
      outletAssignments.where((OutletAssignment a) => a.isActive).toList();

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is StaffMember &&
          other.membershipId == membershipId &&
          other.status == status &&
          other.roles.length == roles.length);

  @override
  int get hashCode => Object.hash(membershipId, status, roles.length);

  /// Omits the name and email deliberately — a `toString()` reaches diagnostic
  /// sinks, and the membership id answers every debugging question personal
  /// data would (Rule 46 hard rule 2).
  @override
  String toString() => 'StaffMember($membershipId, $status)';
}

/// A membership rostered to an outlet.
///
/// CONFERS NO CAPABILITY. An assignment says WHERE somebody works; what they may
/// DO comes from their roles. If an assignment granted anything, the roster
/// screen would be a privilege-escalation path wearing an innocent name
/// (DEC-0031 A2, threat T-14).
@immutable
final class OutletAssignment {
  const OutletAssignment({
    required this.id,
    required this.membershipId,
    required this.outletId,
    required this.isActive,
    this.assignedAt,
    this.revokedAt,
  });

  factory OutletAssignment.fromJson(Map<String, Object?> json) =>
      OutletAssignment(
        id: json['id']! as String,
        membershipId: json['membership_id']! as String,
        outletId: json['outlet_id']! as String,
        isActive: json['is_active'] as bool? ?? true,
        assignedAt: json['assigned_at'] as String?,
        revokedAt: json['revoked_at'] as String?,
      );

  final String id;
  final String membershipId;
  final String outletId;

  /// A revoked assignment is RECORDED, not deleted: "who could work this outlet
  /// in March" must stay answerable.
  final bool isActive;

  final String? assignedAt;
  final String? revokedAt;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutletAssignment &&
          other.id == id &&
          other.isActive == isActive);

  @override
  int get hashCode => Object.hash(id, isActive);

  @override
  String toString() => 'OutletAssignment($id, active: $isActive)';
}
