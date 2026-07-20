/// Removes values that must never leave the device in a diagnostic record.
///
/// This is a LAST LINE OF DEFENCE, not a licence. The primary control is that
/// sensitive values are never placed into a diagnostic in the first place; this
/// exists because "never" is a property of code that people edit.
///
/// The redactor is deliberately AGGRESSIVE and deliberately DUMB. It over-
/// redacts rather than reasoning about whether a particular string is really a
/// token, because a redactor that tries to be clever eventually decides
/// something sensitive is fine.
abstract final class Redaction {
  static const String placeholder = '[REDACTED]';

  /// Field names whose VALUE is always removed, regardless of content.
  ///
  /// Matching is on a normalised name, so `access_token`, `accessToken` and
  /// `ACCESS-TOKEN` are all caught.
  static const Set<String> sensitiveFieldNames = <String>{
    'authorization',
    'cookie',
    'setcookie',
    'token',
    'accesstoken',
    'refreshtoken',
    'sessiontoken',
    'bearer',
    'password',
    'passwordconfirmation',
    'currentpassword',
    'newpassword',
    'secret',
    'apikey',
    'privatekey',
    'otp',
    'otpcode',
    'pin',
    'csrftoken',
    'xsrftoken',
    'trackingtoken',
    // Personal data. Present because a well-meaning diagnostic that includes
    // "the customer we failed to load" is still a disclosure on a device whose
    // logs are readable by any app with the right permission.
    'phone',
    'phonenumber',
    'msisdn',
    'address',
    'fulladdress',
    'email',
    'customername',
    'recipientname',
  };

  static String _normalise(String name) =>
      name.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');

  static bool isSensitiveField(String name) =>
      sensitiveFieldNames.contains(_normalise(name));

  /// Patterns whose MATCHED TEXT is removed wherever it appears in free text.
  static final List<RegExp> _valuePatterns = <RegExp>[
    // Authorization header values, in any casing.
    RegExp(r'Bearer\s+[A-Za-z0-9._~+/=-]+', caseSensitive: false),
    // Laravel Sanctum personal access tokens: "<id>|<40+ chars>".
    RegExp(r'\b\d+\|[A-Za-z0-9]{20,}\b'),
    // Anything long, opaque and secret-shaped.
    RegExp(r'\b[A-Za-z0-9_-]{40,}\b'),
    // Indonesian mobile numbers in any common form.
    RegExp(r'(?<![\w+])(?:\+62|62|08)\d{8,12}(?![\w])'),
    // Email addresses.
    RegExp(r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b'),
  ];

  /// Redact secret-shaped substrings from free text.
  static String text(String input) {
    var output = input;
    for (final pattern in _valuePatterns) {
      output = output.replaceAll(pattern, placeholder);
    }
    return output;
  }

  /// Redact a structured map, by field name and by value shape.
  ///
  /// Nested maps and lists are walked, because a sensitive value one level down
  /// is exactly as disclosed as one at the top.
  static Map<String, Object?> map(Map<String, Object?> input) {
    final output = <String, Object?>{};
    input.forEach((key, value) {
      if (isSensitiveField(key)) {
        output[key] = placeholder;
        return;
      }
      output[key] = _value(value);
    });
    return output;
  }

  static Object? _value(Object? value) => switch (value) {
    final String string => text(string),
    final Map<String, Object?> nested => map(nested),
    final List<Object?> list => list.map(_value).toList(growable: false),
    _ => value,
  };
}
