import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/ops_master_data_scaffold.dart';
import '../routing/ops_routes.dart';
import 'pos_providers.dart';

/// A single order: its lines (captured prices, FR-036), its derived balance
/// (FR-070), its payment history, and the counter actions the cashier may take
/// against it — place, take payment, cancel, view the nota.
///
/// PAID STATE IS NEVER CLAIMED BY THIS SCREEN. A payment is recorded through the
/// server, and only a server-`succeeded` payment moves the balance (FR-064). A
/// pending QRIS payment is shown as pending, and the order stays visibly unpaid.
class PosOrderDetailScreen extends ConsumerStatefulWidget {
  const PosOrderDetailScreen({required this.orderId, super.key});

  final String orderId;

  @override
  ConsumerState<PosOrderDetailScreen> createState() =>
      _PosOrderDetailScreenState();
}

class _PosOrderDetailScreenState extends ConsumerState<PosOrderDetailScreen> {
  OrderDetail? _order;
  List<Payment> _payments = const <Payment>[];
  Failure? _failure;
  bool _loading = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failure = null;
    });
    final orders = ref.read(ordersRepositoryProvider);
    final payments = ref.read(paymentsRepositoryProvider);

    final orderResult = await orders.order(widget.orderId);
    final paymentResult = await payments.payments(widget.orderId);
    if (!mounted) {
      return;
    }
    setState(() {
      _loading = false;
      _order = orderResult.valueOrNull;
      _payments = paymentResult.valueOrNull ?? const <Payment>[];
      _failure = orderResult.failureOrNull ?? paymentResult.failureOrNull;
    });
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    return OpsMasterDataScaffold(
      title: _order?.orderNumber ?? 'Pesanan',
      session: session,
      onBack: () => context.go(OpsRoutes.counter),
      body: _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    final order = _order;
    if (order == null) {
      return StateMessage(
        title: 'Pesanan tidak dapat dimuat',
        description:
            _failure?.message ?? 'Terjadi kesalahan saat memuat pesanan.',
        icon: Icons.error_outline,
        tone: StatusTone.danger,
        recoveryLabel: 'Coba lagi',
        onRecover: _load,
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              Expanded(
                child: Text(
                  order.orderNumber,
                  style: Theme.of(context).textTheme.titleLarge,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              SizedBox(width: AishSpacing.space2),
              StatusChip(
                label: order.status.label,
                icon: Icons.receipt_long_outlined,
                tone: StatusTone.neutral,
              ),
            ],
          ),
          SizedBox(height: AishSpacing.space2),
          StatusChip(
            label: order.paymentState.label,
            icon: order.paymentState == PaymentState.paid
                ? Icons.check_circle_outline
                : Icons.account_balance_wallet_outlined,
            tone: order.paymentState == PaymentState.paid
                ? StatusTone.success
                : StatusTone.warning,
          ),
          SizedBox(height: AishSpacing.space4),
          const Text('Rincian layanan'),
          ...order.lines.map(
            (line) => ListTile(
              dense: true,
              title: Text(line.serviceName),
              subtitle: Text(
                '${_quantityLabel(line)} × ${line.unitPrice.formatted}',
              ),
              trailing: Text(line.subtotal.formatted),
            ),
          ),
          const Divider(),
          _amountRow(context, 'Subtotal', order.summary.subtotal),
          _amountRow(context, 'Diskon', order.summary.discount),
          _amountRow(context, 'Total', order.total, emphasise: true),
          _amountRow(context, 'Dibayar', order.paid),
          _amountRow(
            context,
            'Sisa tagihan',
            order.outstanding,
            emphasise: true,
          ),
          SizedBox(height: AishSpacing.space4),
          if (_payments.isNotEmpty) ...<Widget>[
            const Text('Riwayat pembayaran'),
            ..._payments.map(
              (p) => ListTile(
                dense: true,
                title: Text('${p.kind.label} · ${p.method.label}'),
                subtitle: Text('${p.paymentNumber} · ${p.status.label}'),
                trailing: Text(p.amount.formatted),
              ),
            ),
            SizedBox(height: AishSpacing.space4),
          ],
          _actions(context, order),
        ],
      ),
    );
  }

  Widget _actions(BuildContext context, OrderDetail order) {
    final canPlace = order.status == OrderStatus.draft;
    final canPay =
        order.paymentState != PaymentState.paid &&
        order.status != OrderStatus.cancelled;
    final canCancel =
        order.status == OrderStatus.draft ||
        order.status == OrderStatus.received;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (canPlace)
          PrimaryAction(
            label: 'Terima pesanan',
            icon: Icons.playlist_add_check,
            isBusy: _busy,
            onPressed: _busy ? () {} : _place,
          ),
        if (canPay) ...<Widget>[
          SizedBox(height: AishSpacing.space2),
          PrimaryAction(
            label: 'Terima pembayaran',
            icon: Icons.payments_outlined,
            isBusy: _busy,
            onPressed: _busy ? () {} : () => _openPaymentSheet(order),
          ),
        ],
        SizedBox(height: AishSpacing.space2),
        OutlinedButton.icon(
          onPressed: _busy ? null : _openReceipt,
          icon: const Icon(Icons.description_outlined),
          label: const Text('Lihat nota'),
        ),
        if (canCancel) ...<Widget>[
          SizedBox(height: AishSpacing.space2),
          TextButton.icon(
            onPressed: _busy ? null : () => _openCancelDialog(order),
            icon: const Icon(Icons.cancel_outlined),
            label: const Text('Batalkan pesanan'),
          ),
        ],
      ],
    );
  }

  Future<void> _place() async {
    setState(() => _busy = true);
    final result = await ref
        .read(ordersRepositoryProvider)
        .placeOrder(widget.orderId);
    if (!mounted) return;
    setState(() => _busy = false);
    _afterMutation(result.failureOrNull, 'Pesanan diterima.');
  }

  Future<void> _openPaymentSheet(OrderDetail order) async {
    final outcome = await showModalBottomSheet<_PaymentIntent>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _PaymentSheet(outstanding: order.outstanding),
    );
    if (outcome == null || !mounted) return;

    setState(() => _busy = true);
    final result = await ref
        .read(paymentsRepositoryProvider)
        .recordPayment(
          widget.orderId,
          method: outcome.method.wireValue,
          amountRupiah: outcome.amountRupiah,
          // A fresh reference per payment attempt; a retry of the SAME sheet
          // reuses it via the sheet's own state, not regenerated here.
          clientReference: outcome.clientReference,
        );
    if (!mounted) return;
    setState(() => _busy = false);
    _afterMutation(
      result.failureOrNull,
      outcome.method.isGateway
          ? 'Pembayaran QRIS dicatat, menunggu konfirmasi.'
          : 'Pembayaran dicatat.',
    );
  }

  Future<void> _openReceipt() async {
    setState(() => _busy = true);
    final result = await ref
        .read(ordersRepositoryProvider)
        .receipt(widget.orderId);
    if (!mounted) return;
    setState(() => _busy = false);
    final receipt = result.valueOrNull;
    if (receipt == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.failureOrNull?.message ?? 'Nota tidak dapat dimuat.',
          ),
        ),
      );
      return;
    }
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _ReceiptSheet(receipt: receipt),
    );
  }

  Future<void> _openCancelDialog(OrderDetail order) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Batalkan pesanan'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(
            labelText: 'Alasan pembatalan',
            hintText: 'Wajib diisi',
          ),
          autofocus: true,
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Tutup'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('Batalkan pesanan'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (reason == null || reason.isEmpty || !mounted) return;

    setState(() => _busy = true);
    final result = await ref
        .read(ordersRepositoryProvider)
        .cancelOrder(widget.orderId, reason);
    if (!mounted) return;
    setState(() => _busy = false);
    _afterMutation(result.failureOrNull, 'Pesanan dibatalkan.');
  }

  void _afterMutation(Failure? failure, String successMessage) {
    final messenger = ScaffoldMessenger.of(context);
    if (failure != null) {
      messenger.showSnackBar(SnackBar(content: Text(failure.message)));
      return;
    }
    messenger.showSnackBar(SnackBar(content: Text(successMessage)));
    _load();
  }

  Widget _amountRow(
    BuildContext context,
    String label,
    Rupiah amount, {
    bool emphasise = false,
  }) {
    final style = emphasise
        ? Theme.of(context).textTheme.titleMedium
        : Theme.of(context).textTheme.bodyMedium;
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AishSpacing.space1),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          Expanded(
            child: Text(label, style: style, overflow: TextOverflow.ellipsis),
          ),
          SizedBox(width: AishSpacing.space2),
          Text(amount.formatted, style: style),
        ],
      ),
    );
  }

  String _quantityLabel(OrderLine line) {
    // Quantity is thousandths; show it as a plain decimal for the operator.
    final whole = line.quantityMilli ~/ 1000;
    final frac = line.quantityMilli % 1000;
    if (frac == 0) {
      return '$whole ${line.unit}';
    }
    final fracText = frac
        .toString()
        .padLeft(3, '0')
        .replaceAll(RegExp(r'0+$'), '');
    return '$whole,$fracText ${line.unit}';
  }
}

/// A chosen payment, returned by the payment sheet.
class _PaymentIntent {
  const _PaymentIntent({
    required this.method,
    required this.amountRupiah,
    required this.clientReference,
  });

  final PaymentMethod method;
  final int amountRupiah;
  final String clientReference;
}

/// A bottom sheet to choose a method and an amount. The amount defaults to the
/// full outstanding balance; the server rejects an overpayment (FR-070), so the
/// sheet does not need to enforce the ceiling to be correct — but it starts
/// from the outstanding to make the common full payment one tap.
class _PaymentSheet extends StatefulWidget {
  const _PaymentSheet({required this.outstanding});

  final Rupiah outstanding;

  @override
  State<_PaymentSheet> createState() => _PaymentSheetState();
}

class _PaymentSheetState extends State<_PaymentSheet> {
  late final TextEditingController _amount = TextEditingController(
    text: widget.outstanding.amount.toString(),
  );
  PaymentMethod _method = PaymentMethod.cash;

  // Generated ONCE for this sheet, so a double-tap of "Catat" replays the same
  // client_reference and the server returns the original payment (FR-062).
  final String _clientReference =
      'pay-${DateTime.now().microsecondsSinceEpoch}';

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: AishSpacing.space4,
        right: AishSpacing.space4,
        top: AishSpacing.space4,
        bottom: MediaQuery.of(context).viewInsets.bottom + AishSpacing.space4,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Terima pembayaran',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          SizedBox(height: AishSpacing.space3),
          SegmentedButton<PaymentMethod>(
            segments: PaymentMethod.values
                .map(
                  (m) => ButtonSegment<PaymentMethod>(
                    value: m,
                    label: Text(m.label),
                  ),
                )
                .toList(growable: false),
            selected: <PaymentMethod>{_method},
            onSelectionChanged: (s) => setState(() => _method = s.first),
          ),
          SizedBox(height: AishSpacing.space3),
          TextField(
            controller: _amount,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Nominal (Rupiah)',
              prefixText: 'Rp ',
            ),
          ),
          SizedBox(height: AishSpacing.space4),
          PrimaryAction(
            label: 'Catat pembayaran',
            icon: Icons.check,
            onPressed: _submit,
          ),
        ],
      ),
    );
  }

  void _submit() {
    // Integer Rupiah only; a non-integer input is rejected here rather than
    // sent (Rule 04). The server is still the authority on the amount.
    final raw = _amount.text.trim();
    final parsed = int.tryParse(raw);
    if (parsed == null || parsed <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Nominal harus bilangan bulat Rupiah lebih dari nol.'),
        ),
      );
      return;
    }
    Navigator.of(context).pop(
      _PaymentIntent(
        method: _method,
        amountRupiah: parsed,
        clientReference: _clientReference,
      ),
    );
  }
}

/// The nota (FR-052), rendered ENTIRELY from the server projection — the captured
/// price snapshot and the ledger, never reconstructed from live master data
/// (FR-036).
class _ReceiptSheet extends StatelessWidget {
  const _ReceiptSheet({required this.receipt});

  final Receipt receipt;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.all(AishSpacing.space4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              'Nota ${receipt.orderNumber}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            SizedBox(height: AishSpacing.space3),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                children: <Widget>[
                  ...receipt.lines.map(
                    (line) => ListTile(
                      dense: true,
                      title: Text(line.serviceName),
                      trailing: Text(line.subtotal.formatted),
                    ),
                  ),
                  const Divider(),
                  _row(context, 'Total', receipt.total, emphasise: true),
                  _row(context, 'Dibayar', receipt.paid),
                  _row(context, 'Sisa', receipt.outstanding, emphasise: true),
                  SizedBox(height: AishSpacing.space2),
                  StatusChip(
                    label: receipt.paymentState.label,
                    icon: Icons.account_balance_wallet_outlined,
                    tone: receipt.paymentState == PaymentState.paid
                        ? StatusTone.success
                        : StatusTone.warning,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _row(
    BuildContext context,
    String label,
    Rupiah amount, {
    bool emphasise = false,
  }) {
    final style = emphasise
        ? Theme.of(context).textTheme.titleMedium
        : Theme.of(context).textTheme.bodyMedium;
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AishSpacing.space1),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          Expanded(
            child: Text(label, style: style, overflow: TextOverflow.ellipsis),
          ),
          SizedBox(width: AishSpacing.space2),
          Text(amount.formatted, style: style),
        ],
      ),
    );
  }
}
