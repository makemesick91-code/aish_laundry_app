/// How much of an address the server disclosed (FR-025).
///
/// The SERVER decides this. The client renders what it was given and never
/// reconstructs a fuller value — masking is a server-side control, and a UI that
/// could widen it would be the control's own bypass (Rule 32 hard rule 3).
enum AddressPrecision {
  /// House-number precision.
  full,

  /// District, city, province. Nothing that identifies a building.
  area,

  /// No address at all.
  none;

  /// Parse the server marker, FAILING CLOSED.
  ///
  /// An absent or unrecognised value resolves to [none], never to [full]. A
  /// build that does not understand what it received must assume it holds the
  /// least it is entitled to: the alternative is a client that starts rendering
  /// streets the moment a server adds a marker it has not learned yet.
  static AddressPrecision parse(String? raw) => switch (raw) {
    'full' => AddressPrecision.full,
    'area' => AddressPrecision.area,
    _ => AddressPrecision.none,
  };

  /// Whether the street line is available at this precision.
  bool get includesStreet => this == AddressPrecision.full;
}
