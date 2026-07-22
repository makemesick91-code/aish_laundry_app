import 'address_precision.dart';
import 'customer_detail.dart';

/// The saved addresses of one customer, with the precision the server applied.
///
/// [precision] is carried at LEDGER level as well as per address, because the
/// list shape deliberately contains no location at all: without it a screen
/// could not tell "this caller may see streets on the detail view" from "this
/// caller may not", and would have to find out by opening one and looking.
final class AddressLedger {
  const AddressLedger({required this.addresses, required this.precision});

  final List<CustomerAddress> addresses;

  /// What a DETAIL read would disclose to this caller. The list rows below
  /// carry no location regardless of its value.
  final AddressPrecision precision;

  bool get isEmpty => addresses.isEmpty;

  /// Active addresses only, primary first — the order a counter needs.
  List<CustomerAddress> get live =>
      addresses.where((a) => a.isActive).toList(growable: false)..sort((a, b) {
        if (a.isPrimary != b.isPrimary) return a.isPrimary ? -1 : 1;
        return a.label.compareTo(b.label);
      });

  List<CustomerAddress> get archived =>
      addresses.where((a) => !a.isActive).toList(growable: false);
}
