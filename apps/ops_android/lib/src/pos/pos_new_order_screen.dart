import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/master_data_providers.dart';
import '../master_data/ops_master_data_scaffold.dart';
import '../routing/ops_routes.dart';
import 'pos_providers.dart';

/// A composed order line before the server prices it — WHAT to order and HOW
/// MANY, never a price (FR-051; the server resolves the price from the active
/// price list).
class _DraftLine {
  const _DraftLine({
    required this.service,
    required this.quantityMilli,
  });

  final CatalogService service;
  final int quantityMilli;

  OrderLineInput toInput() => OrderLineInput(
        targetType: 'service',
        targetId: service.id,
        quantityMilli: quantityMilli,
      );

  String get quantityLabel {
    if (service.unitKind == ServiceUnitKind.kiloan) {
      // quantity_milli is milli-kg, and 1 g = 1 milli-kg, so this reads grams.
      return '$quantityMilli gram';
    }
    return '${quantityMilli ~/ 1000} item';
  }
}

/// ORDER INTAKE (FR-048, FR-049 — the shortest primary path): select a customer,
/// add service lines, and create the order. The TOTAL is never computed here —
/// it is shown after the server prices the order (FR-051). Creation is idempotent
/// on a client_reference generated ONCE for this intake, so a double-tap or a
/// retry cannot create two orders (FR-059, FR-062).
class PosNewOrderScreen extends ConsumerStatefulWidget {
  const PosNewOrderScreen({super.key});

  @override
  ConsumerState<PosNewOrderScreen> createState() => _PosNewOrderScreenState();
}

class _PosNewOrderScreenState extends ConsumerState<PosNewOrderScreen> {
  final TextEditingController _customerSearch = TextEditingController();
  final TextEditingController _quantity = TextEditingController();

  // Generated ONCE per intake. Reused on every submit/retry of THIS order, so
  // the server treats a replay as the same order (FR-059/FR-062).
  final String _clientReference = 'order-${DateTime.now().microsecondsSinceEpoch}';

  List<CustomerSummary> _customerResults = const <CustomerSummary>[];
  CustomerSummary? _customer;
  List<CatalogService> _services = const <CatalogService>[];
  CatalogService? _service;
  final List<_DraftLine> _lines = <_DraftLine>[];
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _loadServices();
  }

  @override
  void dispose() {
    _customerSearch.dispose();
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _loadServices() async {
    final result = await ref.read(masterDataRepositoryProvider).services();
    if (!mounted) return;
    setState(() {
      _services = (result.valueOrNull ?? const <CatalogService>[])
          .where((s) => s.isActive)
          .toList(growable: false);
      _service = _services.isNotEmpty ? _services.first : null;
    });
  }

  Future<void> _searchCustomers() async {
    final term = _customerSearch.text.trim();
    if (term.isEmpty) return;
    final result = await ref
        .read(masterDataRepositoryProvider)
        .customers(query: term, status: 'active');
    if (!mounted) return;
    setState(() => _customerResults = result.valueOrNull ?? const <CustomerSummary>[]);
  }

  void _addLine() {
    final service = _service;
    if (service == null) return;
    final qty = int.tryParse(_quantity.text.trim());
    if (qty == null || qty <= 0) {
      _snack('Jumlah harus bilangan bulat lebih dari nol.');
      return;
    }
    // kiloan input is grams (= milli-kg); satuan input is a piece count.
    final quantityMilli =
        service.unitKind == ServiceUnitKind.kiloan ? qty : qty * 1000;
    setState(() {
      _lines.add(_DraftLine(service: service, quantityMilli: quantityMilli));
      _quantity.clear();
    });
  }

  Future<void> _createOrder() async {
    final customer = _customer;
    final session = ref.read(authServiceProvider).current.session;
    final outletId = session?.activeOutlet?.id;
    if (customer == null) {
      _snack('Pilih pelanggan terlebih dahulu.');
      return;
    }
    if (outletId == null) {
      _snack('Pilih outlet aktif terlebih dahulu.');
      return;
    }
    if (_lines.isEmpty) {
      _snack('Tambahkan minimal satu baris layanan.');
      return;
    }

    setState(() => _submitting = true);
    final result = await ref.read(ordersRepositoryProvider).createOrder(
          customerId: customer.id,
          outletId: outletId,
          clientReference: _clientReference,
          lines: _lines.map((l) => l.toInput()).toList(growable: false),
        );
    if (!mounted) return;
    setState(() => _submitting = false);

    final order = result.valueOrNull;
    if (order == null) {
      _snack(result.failureOrNull?.message ?? 'Pesanan gagal dibuat.');
      return;
    }
    // The draft is never silently discarded on failure (above); on success we
    // hand off to the detail screen where the server total and payment live.
    context.go(OpsRoutes.counterOrderDetailFor(order.id));
  }

  void _snack(String message) => ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text(message)));

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    return OpsMasterDataScaffold(
      title: 'Pesanan baru',
      session: session,
      onBack: () => context.go(OpsRoutes.counter),
      body: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          Text('Pelanggan', style: Theme.of(context).textTheme.titleMedium),
          if (_customer != null)
            ListTile(
              leading: const Icon(Icons.person_outline),
              title: Text(_customer!.name),
              subtitle: Text(_customer!.code),
              trailing: TextButton(
                onPressed: () => setState(() => _customer = null),
                child: const Text('Ganti'),
              ),
            )
          else ...<Widget>[
            TextField(
              controller: _customerSearch,
              decoration: InputDecoration(
                labelText: 'Cari nama, kode, atau telepon',
                suffixIcon: IconButton(
                  icon: const Icon(Icons.search),
                  onPressed: _searchCustomers,
                ),
              ),
              onSubmitted: (_) => _searchCustomers(),
            ),
            ..._customerResults.map(
              (c) => ListTile(
                dense: true,
                title: Text(c.name),
                subtitle: Text(c.code),
                onTap: () => setState(() {
                  _customer = c;
                  _customerResults = const <CustomerSummary>[];
                }),
              ),
            ),
          ],
          SizedBox(height: AishSpacing.space4),
          Text('Layanan', style: Theme.of(context).textTheme.titleMedium),
          if (_services.isEmpty)
            const Text('Belum ada layanan aktif pada katalog.')
          else
            Row(
              children: <Widget>[
                Expanded(
                  child: DropdownButton<CatalogService>(
                    isExpanded: true,
                    value: _service,
                    items: _services
                        .map((s) => DropdownMenuItem<CatalogService>(
                              value: s,
                              child: Text('${s.name} (${s.unitKind.label})'),
                            ))
                        .toList(growable: false),
                    onChanged: (s) => setState(() => _service = s),
                  ),
                ),
                SizedBox(width: AishSpacing.space2),
                SizedBox(
                  width: 96,
                  child: TextField(
                    controller: _quantity,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: _service?.unitKind == ServiceUnitKind.kiloan
                          ? 'gram'
                          : 'item',
                    ),
                  ),
                ),
                IconButton(
                  onPressed: _addLine,
                  icon: const Icon(Icons.add_circle_outline),
                ),
              ],
            ),
          SizedBox(height: AishSpacing.space2),
          ..._lines.asMap().entries.map(
                (entry) => ListTile(
                  dense: true,
                  title: Text(entry.value.service.name),
                  subtitle: Text(entry.value.quantityLabel),
                  trailing: IconButton(
                    icon: const Icon(Icons.delete_outline),
                    onPressed: () => setState(() => _lines.removeAt(entry.key)),
                  ),
                ),
              ),
          SizedBox(height: AishSpacing.space6),
          PrimaryAction(
            label: 'Buat pesanan',
            icon: Icons.check,
            isBusy: _submitting,
            onPressed: _submitting ? () {} : _createOrder,
          ),
          SizedBox(height: AishSpacing.space2),
          const Text(
            'Total dihitung oleh server dari daftar harga aktif setelah pesanan '
            'dibuat.',
            style: TextStyle(fontSize: 12),
          ),
        ],
      ),
    );
  }
}
