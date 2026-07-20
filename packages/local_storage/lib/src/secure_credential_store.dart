import 'package:aish_core/aish_core.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:meta/meta.dart';

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
  static const String lastActiveTenantId = 'last_active_tenant_id';
  static const String lastActiveOutletId = 'last_active_outlet_id';
}

/// Platform secure storage implementation.
final class PlatformSecureCredentialStore implements SecureCredentialStore {
  PlatformSecureCredentialStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  @override
  Future<Result<String?>> read({
    required StorageNamespace namespace,
    required String key,
  }) => _guard(() => _storage.read(key: namespace.qualify(key)), 'read');

  @override
  Future<Result<void>> write({
    required StorageNamespace namespace,
    required String key,
    required String value,
  }) => _guard(
    () => _storage.write(key: namespace.qualify(key), value: value),
    'write',
  );

  @override
  Future<Result<void>> delete({
    required StorageNamespace namespace,
    required String key,
  }) => _guard(() => _storage.delete(key: namespace.qualify(key)), 'delete');

  @override
  Future<Result<void>> clearNamespace(StorageNamespace namespace) =>
      _guard(() async {
        final all = await _storage.readAll();
        for (final key in all.keys.where(namespace.owns)) {
          await _storage.delete(key: key);
        }
      }, 'clearNamespace');

  @override
  Future<Result<void>> clearOnLogout() =>
      _guard(() => _storage.deleteAll(), 'clearOnLogout');

  /// Wrap a platform call, converting a failure into a [Result].
  ///
  /// The message names the OPERATION only. It never carries the key or the
  /// value, because a storage exception message is a diagnostics sink and a key
  /// name discloses which credentials exist.
  Future<Result<T>> _guard<T>(
    Future<T> Function() operation,
    String operationName,
  ) async {
    try {
      return Result<T>.ok(await operation());
    } on Object {
      return Result<T>.err(
        Failure(
          kind: FailureKind.storage,
          message: 'Secure storage $operationName failed.',
        ),
      );
    }
  }
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
