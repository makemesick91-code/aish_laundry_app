import 'package:aish_networking/aish_networking.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// The Ops surface's access to Step 4 master data.
///
/// Overridden at composition root with a repository built from the surface's
/// authenticated [ApiClient], and overridden in tests with one built over a
/// scripted transport. Throwing by default is deliberate: a surface that
/// silently got an unconfigured repository would fail at the first request with
/// a confusing transport error instead of at wiring time.
final Provider<MasterDataRepository> masterDataRepositoryProvider =
    Provider<MasterDataRepository>(
      (ref) => throw UnimplementedError(
        'masterDataRepositoryProvider must be overridden with a repository '
        "built from the surface's ApiClient.",
      ),
    );
