import 'package:meta/meta.dart';

import 'failure.dart';
import 'result.dart';
import 'storage_namespace.dart';

/// Read and write credentials, always inside a namespace.
///
/// Every method takes a [StorageNamespace]. There is no un-namespaced overload,
/// which is what prevents a caller from writing a key that is shared between two
/// tenants "just this once".
///
/// No method returns anything that identifies a credential's VALUE in an error
/// path, and no implementation may log one.
abstract interface class SecureCredentialStore {
  Future<Result<String?>> read({
    required StorageNamespace namespace,
    required String key,
  });

  Future<Result<void>> write({
    required StorageNamespace namespace,
    required String key,
    required String value,
  });

  Future<Result<void>> delete({
    required StorageNamespace namespace,
    required String key,
  });

  /// Remove everything in [namespace] and nothing outside it.
  Future<Result<void>> clearNamespace(StorageNamespace namespace);

  /// Remove EVERY credential this application holds.
  ///
  /// Called on logout. It clears all namespaces rather than only the active
  /// one, because a user signing out on a shared counter device expects the
  /// device to hold nothing of theirs — including the tenant they switched away
  /// from twenty minutes ago.
  Future<Result<void>> clearOnLogout();
}

/// Well-known credential keys. Values are never constructed ad hoc at call
/// sites, so a typo cannot silently create a second, never-cleared key.
abstract final class CredentialKeys {
  static const String sessionToken = 'session_token';
  static const String deviceIdentifier = 'device_identifier';

  /// Which user's namespace holds the session credential.
  ///
  /// Lives in the DEVICE namespace, because at startup there is no identity yet
  /// and therefore no user namespace to look in. It is a pointer, never a
  /// credential: knowing it grants nothing, and the token it points at is still
  /// server-verified before any session is considered restored.
  static const String activeUserId = 'active_user_id';
  static const String lastActiveTenantId = 'last_active_tenant_id';
  static const String lastActiveOutletId = 'last_active_outlet_id';
}

/// An in-memory fake for tests.
///
/// Lives in the production package rather than in test code on purpose: it must
/// satisfy exactly the same interface, and keeping it beside the real
/// implementation makes a divergence a compile error.
@visibleForTesting
final class InMemoryCredentialStore implements SecureCredentialStore {
  final Map<String, String> _entries = <String, String>{};

  /// Set to make every operation fail, so a caller's storage-failure path can
  /// be exercised.
  bool failEverything = false;

  /// Qualified keys currently held. Test-only introspection.
  Iterable<String> get keys => _entries.keys;

  @override
  Future<Result<String?>> read({
    required StorageNamespace namespace,
    required String key,
  }) async => failEverything
      ? const Result<String?>.err(
          Failure(
            kind: FailureKind.storage,
            message: 'Secure storage read failed.',
          ),
        )
      : Result<String?>.ok(_entries[namespace.qualify(key)]);

  @override
  Future<Result<void>> write({
    required StorageNamespace namespace,
    required String key,
    required String value,
  }) async {
    if (failEverything) {
      return const Result<void>.err(
        Failure(
          kind: FailureKind.storage,
          message: 'Secure storage write failed.',
        ),
      );
    }
    _entries[namespace.qualify(key)] = value;
    return const Result<void>.ok(null);
  }

  @override
  Future<Result<void>> delete({
    required StorageNamespace namespace,
    required String key,
  }) async {
    if (failEverything) {
      return const Result<void>.err(
        Failure(
          kind: FailureKind.storage,
          message: 'Secure storage delete failed.',
        ),
      );
    }
    _entries.remove(namespace.qualify(key));
    return const Result<void>.ok(null);
  }

  @override
  Future<Result<void>> clearNamespace(StorageNamespace namespace) async {
    if (failEverything) {
      return const Result<void>.err(
        Failure(
          kind: FailureKind.storage,
          message: 'Secure storage clear failed.',
        ),
      );
    }
    _entries.removeWhere((key, _) => namespace.owns(key));
    return const Result<void>.ok(null);
  }

  @override
  Future<Result<void>> clearOnLogout() async {
    if (failEverything) {
      return const Result<void>.err(
        Failure(
          kind: FailureKind.storage,
          message: 'Secure storage clear failed.',
        ),
      );
    }
    _entries.clear();
    return const Result<void>.ok(null);
  }
}

/// A store that persists NOTHING, for a surface that holds no credential.
///
/// Console Web is the case this exists for. Its credential is a first-party
/// `HttpOnly` cookie the browser owns; there is no token to keep, and the
/// server-side session already remembers the selected tenant for cookie
/// clients. Giving that surface a persistent store would mean shipping
/// browser-backed storage into a build that has nothing legitimate to put in
/// it — and on web, `flutter_secure_storage` IS `localStorage`, which Rule 38
/// hard rule 2 forbids for credential material.
///
/// Distinct from [InMemoryCredentialStore], which is a TEST double. This is a
/// production choice with a documented reason, and naming them differently
/// keeps a test-only type out of a composition root.
final class EphemeralCredentialStore implements SecureCredentialStore {
  final Map<String, String> _entries = <String, String>{};

  @override
  Future<Result<String?>> read({
    required StorageNamespace namespace,
    required String key,
  }) async => Result<String?>.ok(_entries[namespace.qualify(key)]);

  @override
  Future<Result<void>> write({
    required StorageNamespace namespace,
    required String key,
    required String value,
  }) async {
    _entries[namespace.qualify(key)] = value;
    return const Result<void>.ok(null);
  }

  @override
  Future<Result<void>> delete({
    required StorageNamespace namespace,
    required String key,
  }) async {
    _entries.remove(namespace.qualify(key));
    return const Result<void>.ok(null);
  }

  @override
  Future<Result<void>> clearNamespace(StorageNamespace namespace) async {
    _entries.removeWhere((key, _) => namespace.owns(key));
    return const Result<void>.ok(null);
  }

  @override
  Future<Result<void>> clearOnLogout() async {
    _entries.clear();
    return const Result<void>.ok(null);
  }
}
