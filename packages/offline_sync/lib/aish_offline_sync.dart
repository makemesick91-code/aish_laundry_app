/// Offline synchronization INTERFACES and state taxonomy.
///
/// SCOPE, stated plainly: this package contains no queue implementation, no
/// persistence, no retry engine, and no order or payment operation of any kind.
/// Step 3 defines the shape that Step 5 and later must fill; defining the shape
/// now is what stops each surface inventing its own.
///
/// **NOT IMPLEMENTED.** Nothing here synchronises anything. A declared interface
/// is an obligation, never an achievement.
library;

export 'src/sync_operation.dart';
export 'src/sync_queue.dart';
export 'src/sync_state.dart';
