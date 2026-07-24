import 'package:aish_core/aish_core.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// The Ops surface's access to the Step 6 production API (DEC-0037).
///
/// Built from the surface's authenticated [ApiClient], so every request carries
/// the credential and tenant/outlet context the operator signed in with. The
/// production default IS the real repository; a test overrides it with one over
/// a scripted transport.
final Provider<ProductionRepository> productionRepositoryProvider =
    Provider<ProductionRepository>(
      (ref) => ProductionRepository(ref.watch(apiClientProvider)),
    );

/// The platform keystore-backed store the durable command queue persists to.
///
/// A DEDICATED provider so a test can substitute an in-memory double. On a
/// device this is the same keystore-backed encryption boundary the credentials
/// use; the queue's keys are namespaced and never collide with them.
final Provider<SecureCredentialStore> secureCredentialStoreProvider =
    Provider<SecureCredentialStore>((ref) => PlatformSecureCredentialStore());

/// How the sync worker learns whether the device is online.
///
/// The default makes NO false claim of detection (no connectivity plugin is
/// added — Rule 37); it reports online and lets a send discover the truth. A
/// test injects a fake to drive offline/online deterministically.
final Provider<ConnectivityMonitor> productionConnectivityProvider =
    Provider<ConnectivityMonitor>(
      (ref) => const OptimisticConnectivityMonitor(),
    );

/// The clock the worker uses for backoff windows. Overridable in tests.
final Provider<Clock> productionClockProvider = Provider<Clock>(
  (ref) => const SystemClock(),
);

/// The durable queue and its sync worker, scoped to the CURRENT (user, tenant).
///
/// Rebuilt whenever the auth state changes, so a tenant switch produces a queue
/// in the NEW tenant's namespace and a worker bound to it — a drain can never be
/// attributed to the tenant the user just switched away from (Rule 02, Rule 20
/// rule 6). Returns null before a tenant context exists.
final Provider<ProductionRuntime?>
productionRuntimeProvider = Provider<ProductionRuntime?>((ref) {
  // Rebuild trigger: the auth state stream emits on login, tenant switch and
  // revocation. The session itself is read from `current`, always up to date.
  ref.watch(authStatesProvider);
  final session = ref.watch(authServiceProvider).current.session;
  if (session == null || !session.hasTenantContext) {
    return null;
  }

  final queue = ProductionCommandQueue(
    store: ref.watch(secureCredentialStoreProvider),
    userId: session.user.id,
    tenantId: session.activeTenant!.id,
  );
  final worker = ProductionSyncWorker(
    repository: ref.watch(productionRepositoryProvider),
    queue: queue,
    clock: ref.watch(productionClockProvider),
    connectivity: ref.watch(productionConnectivityProvider),
  );
  ref.onDispose(worker.stop);

  return ProductionRuntime(
    queue: queue,
    worker: worker,
    userId: session.user.id,
    tenantId: session.activeTenant!.id,
    outletId: session.activeOutlet?.id,
  );
});

/// Rebuild trigger for the runtime: the raw auth-state stream.
final StreamProvider<Object?> authStatesProvider = StreamProvider<Object?>(
  (ref) => ref.watch(authServiceProvider).states,
);

/// The queue and worker for one working context, held together so screens share
/// ONE worker (and therefore one drain loop) rather than each starting their own.
final class ProductionRuntime {
  const ProductionRuntime({
    required this.queue,
    required this.worker,
    required this.userId,
    required this.tenantId,
    this.outletId,
  });

  final ProductionCommandQueue queue;
  final ProductionSyncWorker worker;
  final String userId;
  final String tenantId;
  final String? outletId;
}
