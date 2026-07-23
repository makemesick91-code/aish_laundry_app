import 'package:aish_networking/aish_networking.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// The Ops surface's access to Step 5 orders and payments (DEC-0035).
///
/// Built from the surface's authenticated [ApiClient], so every request carries
/// the same credential and tenant/outlet context the cashier signed in with. The
/// production default IS the real repository (the DEC-0032 lesson); a test
/// overrides it with a repository over a scripted transport.
final Provider<OrdersRepository> ordersRepositoryProvider =
    Provider<OrdersRepository>(
      (ref) => OrdersRepository(ref.watch(apiClientProvider)),
    );

final Provider<PaymentsRepository> paymentsRepositoryProvider =
    Provider<PaymentsRepository>(
      (ref) => PaymentsRepository(ref.watch(apiClientProvider)),
    );
