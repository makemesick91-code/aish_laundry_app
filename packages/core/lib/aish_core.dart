/// Pure Dart foundation shared by every Aish Laundry App surface.
///
/// This library MUST NOT import `dart:ui` or `package:flutter`. That constraint
/// is what lets the error taxonomy, the clock and the environment configuration
/// be unit-tested without a widget binding, and it is asserted by a test rather
/// than trusted.
library;

export 'src/app_version.dart';
export 'src/clock.dart';
export 'src/correlation_id.dart';
export 'src/environment.dart';
export 'src/failure.dart';
export 'src/result.dart';
