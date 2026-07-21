/// Identity, tenancy, authorization, and LAUNDRY MASTER DATA projections.
///
/// SCOPE BOUNDARY, stated so it cannot be crossed by accident: this package
/// carries NO order, payment, invoice, receipt, production, tracking, delivery,
/// reminder, finance, loyalty or subscription type. Those belong to Step 5 and
/// later, and a type added here early is roadmap leakage rather than a head
/// start (CLAUDE.md §3, Rule 42).
///
/// Step 3 delivered the identity and tenancy projections. Step 4 adds master
/// data under DEC-0028 and DEC-0030: customers, the service catalogue, price
/// lists, outlet configuration, and staff assignment.
///
/// Every type here is a PROJECTION of server state. The backend is the
/// authorization authority; these classes describe what the client was TOLD,
/// never what the client decided. In particular, a role list on a projection is
/// for display — branching on it would put an access-control decision in a
/// client, and hiding a control is never the control (Rule 40 hard rule 2).
library;

export 'src/effective_permissions.dart';
export 'src/laundry_brand.dart';
export 'src/master_data/catalog_item.dart';
export 'src/master_data/customer_summary.dart';
export 'src/master_data/outlet_master_data.dart';
export 'src/master_data/price_list.dart';
export 'src/master_data/rupiah.dart';
export 'src/master_data/staff_assignment.dart';
export 'src/membership.dart';
export 'src/outlet.dart';
export 'src/permission.dart';
export 'src/role.dart';
export 'src/session_state.dart';
export 'src/tenant.dart';
export 'src/user.dart';
