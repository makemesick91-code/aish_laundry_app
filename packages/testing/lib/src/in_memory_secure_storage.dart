import 'package:aish_local_storage/aish_local_storage.dart';

/// The in-memory credential store, re-exported under the name tests use.
///
/// The implementation lives beside the real one in `aish_local_storage` so the
/// two cannot drift: a change to [SecureCredentialStore] breaks both at compile
/// time. This alias exists only so a test reads `InMemorySecureStorage()` rather
/// than reaching into another package's `@visibleForTesting` surface.
typedef InMemorySecureStorage = InMemoryCredentialStore;
