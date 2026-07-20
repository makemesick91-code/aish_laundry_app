import 'package:meta/meta.dart';

/// Build identity of the running surface.
///
/// Carried in diagnostics so a report can be tied to a specific build. Contains
/// no personal datum and no credential, which is what makes it safe to emit.
@immutable
final class AppVersion {
  const AppVersion({
    required this.surface,
    required this.semanticVersion,
    required this.buildNumber,
  });

  /// Which client surface this is, e.g. `customer_android`.
  final String surface;

  final String semanticVersion;

  final String buildNumber;

  String get display => '$surface $semanticVersion+$buildNumber';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is AppVersion &&
          other.surface == surface &&
          other.semanticVersion == semanticVersion &&
          other.buildNumber == buildNumber);

  @override
  int get hashCode => Object.hash(surface, semanticVersion, buildNumber);

  @override
  String toString() => display;
}
