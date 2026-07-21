import 'package:meta/meta.dart';

/// AN AMOUNT OF MONEY, AS A WHOLE NUMBER OF RUPIAH (FR-037, Rule 04).
///
/// WHY THIS TYPE EXISTS RATHER THAN A BARE `int`
/// ---------------------------------------------
/// An `int` in a widget tree is indistinguishable from a quantity, a page
/// number, or a display order, and Dart will happily let any of them be
/// formatted with a currency prefix. Making money a type means a surface cannot
/// render a count as a price by accident, and cannot add a price to a quantity
/// at all.
///
/// WHY THERE IS NO `double` ANYWHERE ON IT
/// ---------------------------------------
/// Rule 04 hard rule 2 forbids binary floating point in any money path, and a
/// client is part of that path: a total computed in `double` for display, then
/// sent back, is how a rounding error reaches the server. [parse] refuses a
/// fractional value rather than truncating it.
///
/// WHY IT DOES NOT DO ARITHMETIC
/// -----------------------------
/// There is deliberately no `+`, no `*`, and no `applyDiscount`. Totals are
/// computed and authoritative ON THE SERVER; a client total is display only
/// (Rule 04, supporting expectations). Offering arithmetic here would invite a
/// surface to compute an order total, and an order is Step 5 regardless.
@immutable
final class Rupiah implements Comparable<Rupiah> {
  const Rupiah(this.amount) : assert(amount >= 0, 'A price may not be negative');

  /// Parse a wire value.
  ///
  /// Accepts an `int` and an exact integer string. Refuses a `double`, a
  /// decimal string, and a formatted string like "Rp17.500" — money is never
  /// inferred from a display value.
  factory Rupiah.parse(Object? value) {
    if (value is int) {
      return Rupiah(value);
    }

    if (value is String && RegExp(r'^\d+$').hasMatch(value)) {
      final parsed = int.tryParse(value);
      if (parsed != null) {
        return Rupiah(parsed);
      }
    }

    throw ArgumentError.value(
      value,
      'amount_rupiah',
      'Nilai uang harus berupa bilangan bulat Rupiah.',
    );
  }

  /// Whole Rupiah. The smallest unit; there is nothing below it.
  final int amount;

  static const Rupiah zero = Rupiah(0);

  /// Indonesian display form, e.g. `Rp17.500`.
  ///
  /// A VIEW CONCERN APPLIED TO AN INTEGER, and the one-way direction matters:
  /// this produces a string for a human, and nothing in the product ever parses
  /// one back into money.
  String get formatted {
    final digits = amount.toString();
    final buffer = StringBuffer();

    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && (digits.length - i) % 3 == 0) {
        buffer.write('.');
      }
      buffer.write(digits[i]);
    }

    return 'Rp$buffer';
  }

  @override
  int compareTo(Rupiah other) => amount.compareTo(other.amount);

  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is Rupiah && other.amount == amount);

  @override
  int get hashCode => amount.hashCode;

  @override
  String toString() => 'Rupiah($amount)';
}
