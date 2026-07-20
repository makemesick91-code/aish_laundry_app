/// Redacted diagnostics for the Aish Laundry App.
///
/// The single rule: a diagnostic record carries a correlation identifier, an app
/// version, an environment name, and a developer-facing message — and NOTHING
/// else. No token, no password, no cookie, no OTP, no phone number, no address,
/// no customer name. Rule 03 rule 20 is absolute, and it has no debug-level
/// exemption and no "temporarily, on my branch" exemption.
library;

export 'src/diagnostic_event.dart';
export 'src/diagnostics_recorder.dart';
export 'src/redaction.dart';
