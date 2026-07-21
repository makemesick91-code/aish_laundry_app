/// The client's model of authentication and session state.
///
/// This package does NOT authorize anything. It records what the server said
/// about the session, so a surface can render an honest state and offer an
/// honest recovery. Every decision that matters was made on the server.
library;

export 'src/auth_runtime.dart';
export 'src/auth_service.dart';
export 'src/auth_state.dart';
export 'src/backend_auth_service.dart';
export 'src/session_credentials.dart';
