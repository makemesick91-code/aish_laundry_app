import 'package:aish_networking/aish_networking.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// The Ops surface's access to Step 4 master data.
///
/// Built from the surface's authenticated [ApiClient], so every master-data
/// request carries the same credential and the same tenant context the user
/// signed in with. A test overrides it with a repository over a scripted
/// transport.
///
/// THIS PROVIDER PREVIOUSLY THREW `UnimplementedError` and was overridden only
/// in tests. `main` overrides `environmentProvider` and nothing else, so every
/// production master-data screen — the counter, customer detail, the catalogue,
/// outlets, the roster — threw the moment it was opened in a real build. The
/// widget suite stayed green throughout, because each test supplied the
/// repository through this same provider.
///
/// That is exactly the defect DEC-0032 records for `authServiceProvider`,
/// repeated one layer up. It is fixed the same way: the production default IS
/// the real thing, and a test overrides it rather than being the only thing
/// that ever supplied it.
final Provider<MasterDataRepository> masterDataRepositoryProvider =
    Provider<MasterDataRepository>(
      (ref) => MasterDataRepository(ref.watch(apiClientProvider)),
    );
