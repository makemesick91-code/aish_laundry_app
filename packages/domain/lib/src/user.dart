import 'package:meta/meta.dart';

/// The authenticated user, as the server describes them.
///
/// This is a PROJECTION, not an account record. It carries the minimum a client
/// needs to render "who am I" and nothing more — no password material, no token,
/// no session secret, and no address.
@immutable
final class User {
  const User({
    required this.id,
    required this.displayName,
    this.maskedPhone,
    this.email,
  });

  final String id;

  final String displayName;

  /// Phone in MASKED form only.
  ///
  /// The client never holds the full number for display. Masking is applied by
  /// the server, because a client that receives the full value and masks it for
  /// rendering has still received it, and it will end up in a log eventually.
  final String? maskedPhone;

  final String? email;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is User &&
          other.id == id &&
          other.displayName == displayName &&
          other.maskedPhone == maskedPhone &&
          other.email == email);

  @override
  int get hashCode => Object.hash(id, displayName, maskedPhone, email);

  @override
  String toString() => 'User($id, $displayName)';
}
