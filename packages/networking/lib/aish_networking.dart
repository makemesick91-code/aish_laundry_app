/// HTTP access to the Aish Laundry App `/api/v1` surface.
///
/// The single rule that governs this package: THE SERVER IS THE AUTHORITY. This
/// code transports requests and classifies responses. It never decides whether
/// an action is permitted, and it never converts an unrecognised server answer
/// into a permissive one.
library;

export 'src/api_client.dart';
export 'src/api_endpoints.dart';
export 'src/api_error_code.dart';
export 'src/api_response.dart';
export 'src/error_mapper.dart';
