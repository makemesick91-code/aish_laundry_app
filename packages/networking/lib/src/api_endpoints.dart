/// The `/api/v1` paths this runtime is permitted to call.
///
/// The list is exhaustive on purpose, and its exhaustiveness is the control: a
/// client constant is how a feature quietly acquires a foothold before its Step.
/// Every path below corresponds to a route the backend actually registers.
///
/// Step 3 registered authentication, tenancy and RBAC. Step 4 adds LAUNDRY
/// MASTER DATA under DEC-0028 and DEC-0030 — customers, consent, the service
/// catalogue, price lists, outlet master data, and staff assignment. Step 5 adds
/// the ORDER and PAYMENT surface under DEC-0035 — order intake, the nota, and the
/// append-only payment ledger.
///
/// STILL ABSENT, AND ABSENT ON PURPOSE: any path for an invoice, production,
/// tracking, a pickup, a delivery, a reminder, or a subscription. Those belong to
/// Step 6 and later (CLAUDE.md §3 — roadmap lock, Rule 42). There is likewise no
/// export and no bulk path, because the backend registers neither (threats T-19,
/// T-20).
abstract final class ApiEndpoints {
  // Operational probes.
  static const String health = 'health';
  static const String readiness = 'readiness';

  // Authentication.
  static const String login = 'auth/login';
  static const String logout = 'auth/logout';
  static const String me = 'auth/me';
  static const String passwordResetRequest = 'auth/password-reset/request';
  static const String passwordResetComplete = 'auth/password-reset/complete';

  // Session self-service.
  static const String sessions = 'sessions';
  static const String sessionsRevokeOthers = 'sessions/revoke-others';

  // Tenant and outlet context.
  static const String contextTenants = 'context/tenants';
  static const String contextTenant = 'context/tenant';
  static const String contextOutlets = 'context/outlets';
  static const String contextOutlet = 'context/outlet';

  // Tenancy and authorization.
  static const String membershipsCurrent = 'memberships/current';
  static const String permissions = 'authorization/permissions';

  // -------------------------------------------------------------------------
  // STEP 4 — LAUNDRY MASTER DATA (FR-021 … FR-047)
  // -------------------------------------------------------------------------

  // Customers (FR-021 … FR-030). No destroy path: a customer a future order
  // references must stay resolvable, so archival replaces deletion (T-18).
  static const String customers = 'customers';
  static String customer(String id) => 'customers/$id';
  static String customerArchive(String id) => 'customers/$id/archive';

  // Consent (FR-027, FR-028). Read and APPEND only — there is deliberately no
  // update or delete path, because a withdrawal is a NEW record and never an
  // edit of an old one (invariant C5).
  static String customerConsents(String id) => 'customers/$id/consents';

  // FR-024. Addresses are referenced by opaque identifier only — never by any
  // component of the address itself. A path is logged by every proxy in front of
  // the application, kept in browser history, and passed on in a referrer.
  static String customerAddresses(String customerId) =>
      'customers/$customerId/addresses';

  static String customerAddress(String customerId, String addressId) =>
      'customers/$customerId/addresses/$addressId';

  static String customerAddressArchive(String customerId, String addressId) =>
      'customers/$customerId/addresses/$addressId/archive';

  static String customerAddressReactivate(
    String customerId,
    String addressId,
  ) => 'customers/$customerId/addresses/$addressId/reactivate';

  // Service catalogue (FR-031 … FR-033, FR-040). Carries no price: what a
  // service COSTS lives on a per-brand price list (FR-034).
  static const String serviceCategories = 'service-categories';
  static String serviceCategory(String id) => 'service-categories/$id';
  static const String services = 'services';
  static String service(String id) => 'services/$id';
  static const String servicePackages = 'service-packages';
  static String servicePackage(String id) => 'service-packages/$id';
  static String servicePackageItems(String id) => 'service-packages/$id/items';
  static const String serviceAddons = 'service-addons';
  static String serviceAddon(String id) => 'service-addons/$id';

  // Per-brand price lists (FR-034 … FR-040). Publishing has its own path and
  // its own permission because it is the irreversible act.
  static const String priceLists = 'price-lists';
  static String priceList(String id) => 'price-lists/$id';
  static String priceListPublish(String id) => 'price-lists/$id/publish';
  static String priceListItems(String id) => 'price-lists/$id/items';
  static String priceListItem(String listId, String itemId) =>
      'price-lists/$listId/items/$itemId';

  // Outlet master data (FR-041 … FR-047). Satellites are nested under their
  // outlet so the tenant-scoped outlet lookup happens before the satellite is
  // addressed at all.
  static String outletMasterData(String id) => 'outlets/$id/master-data';
  static String outletServiceZones(String id) => 'outlets/$id/service-zones';
  static String outletServiceZone(String outletId, String zoneId) =>
      'outlets/$outletId/service-zones/$zoneId';
  static String outletShifts(String id) => 'outlets/$id/shifts';
  static String outletShift(String outletId, String shiftId) =>
      'outlets/$outletId/shifts/$shiftId';
  static String outletPrinters(String id) => 'outlets/$id/printers';
  static String outletPrinter(String outletId, String printerId) =>
      'outlets/$outletId/printers/$printerId';

  // FR-046 — tenant-wide, not per-outlet: a custody-proof requirement that
  // varied by outlet would mean a parcel's evidence requirement depended on
  // which counter it passed through (Rule 09 hard rule 2).
  static const String proofPolicy = 'proof-policy';

  // Staff assignment (ROADMAP Step 4 scope, FR-018). Assigning an OUTLET says
  // where somebody works; assigning a ROLE confers capability. Separate paths,
  // separate permissions.
  static const String staff = 'staff';
  static String staffMember(String id) => 'staff/$id';
  static String staffOutlets(String id) => 'staff/$id/outlets';
  static String staffOutletRevoke(String membershipId, String assignmentId) =>
      'staff/$membershipId/outlets/$assignmentId/revoke';
  static String staffRoles(String id) => 'staff/$id/roles';
  static String staffRole(String membershipId, String roleKey) =>
      'staff/$membershipId/roles/$roleKey';

  // -------------------------------------------------------------------------
  // STEP 5 — POS, ORDER, AND PAYMENT FOUNDATION (FR-048 … FR-070, DEC-0035)
  // -------------------------------------------------------------------------

  // Orders (FR-048 … FR-060). No destroy path: an order is cancelled with a
  // reason, never deleted, so its financial history survives (FR-066). Placing
  // and cancelling have their own paths and permissions.
  static const String orders = 'orders';
  static String order(String id) => 'orders/$id';
  static String orderPlace(String id) => 'orders/$id/place';
  static String orderCancel(String id) => 'orders/$id/cancel';
  static String orderReceipt(String id) => 'orders/$id/receipt';

  // Payments (FR-061 … FR-069). Read and APPEND only — there is no update and no
  // destroy path, because a correction is a reversal (a new row) and the ledger
  // is append-only (FR-066, FR-067).
  static String orderPayments(String orderId) => 'orders/$orderId/payments';
  static String paymentConfirm(String paymentId) => 'payments/$paymentId/confirm';
  static String paymentReverse(String paymentId) => 'payments/$paymentId/reverse';
}
