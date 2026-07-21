import 'package:aish_core/aish_core.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

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
