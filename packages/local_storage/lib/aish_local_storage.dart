/// Platform-backed credential storage for the Aish Laundry App.
///
/// The `SecureCredentialStore` ABSTRACTION lives in `aish_core`, which is pure
/// Dart. Only the platform-backed implementation lives here, and it is confined
/// to this package on purpose: `flutter_secure_storage` is a plugin, and a
/// plugin in a package's dependency graph is REGISTERED into every build that
/// graph reaches — it cannot be tree-shaken away by not calling it.
///
/// On web that plugin is backed by `localStorage`/`sessionStorage`, which
/// Rule 38 hard rule 2 forbids for credential material. So Console Web must not
/// depend on this package at all; it uses `EphemeralCredentialStore` from
/// `aish_core`. `scripts/scan-web-build.py` enforces that from the built
/// artefact rather than trusting the dependency list.
library;

export 'src/secure_credential_store.dart';
