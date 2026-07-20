/// Identity, tenancy and authorization projections for Step 3.
///
/// SCOPE BOUNDARY, stated so it cannot be crossed by accident: this package
/// carries NO order, payment, customer, service, price, tracking, delivery,
/// reminder, finance, loyalty or subscription type. Those belong to Step 4 and
/// later. A type added here early is roadmap leakage, not a head start.
///
/// Every type here is a PROJECTION of server state. The backend is the
/// authorization authority; these classes describe what the client was told,
/// never what the client decided.
library;

export 'src/effective_permissions.dart';
export 'src/laundry_brand.dart';
export 'src/membership.dart';
export 'src/outlet.dart';
export 'src/permission.dart';
export 'src/role.dart';
export 'src/session_state.dart';
export 'src/tenant.dart';
export 'src/user.dart';
