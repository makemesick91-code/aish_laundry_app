import 'package:meta/meta.dart';

/// A decoded successful response envelope.
///
/// The backend guarantees the shape
/// `{ "data": ..., "meta": { "request_id": "..." } }` for every success, so the
/// client decodes it in exactly one place rather than per call site.
@immutable
final class ApiSuccess {
  const ApiSuccess({required this.data, this.requestId});

  /// The `data` member. Typed loosely here because shaping it into a domain
  /// object belongs to the caller that knows the endpoint.
  final Object? data;

  /// `meta.request_id` — safe to surface to a user for support triage. It is an
  /// identifier, not a credential.
  final String? requestId;

  Map<String, Object?> get dataAsMap =>
      data is Map<String, Object?> ? data! as Map<String, Object?> : const {};

  List<Object?> get dataAsList =>
      data is List ? data! as List<Object?> : const [];
}
