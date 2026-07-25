import 'package:aish_networking/aish_networking.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// The Ops surface's access to the Step 7 tracking and notification API
/// (FR-086 … FR-099, DEC-0039).
///
/// Built from the surface's authenticated [ApiClient], so every request carries
/// the credential and the tenant/outlet context the operator signed in with.
/// The production default IS the real repository; a test overrides it with one
/// over a scripted transport.
final Provider<TrackingRepository> trackingRepositoryProvider =
    Provider<TrackingRepository>(
      (ref) => TrackingRepository(ref.watch(apiClientProvider)),
    );
