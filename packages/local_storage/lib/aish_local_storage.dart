/// Credential and context storage for the Aish Laundry App.
///
/// There is deliberately NO plaintext path. The only persistent implementation
/// is backed by platform secure storage (Android Keystore / EncryptedSharedPrefs
/// and the iOS Keychain), and the only alternative is an in-memory fake used by
/// tests. A `SharedPreferences`-shaped option is not provided, because the
/// moment one exists somebody will reach for it "just for the refresh token".
library;

export 'src/secure_credential_store.dart';
export 'src/storage_namespace.dart';
