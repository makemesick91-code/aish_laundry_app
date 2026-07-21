import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';

import 'api_client.dart';
import 'api_endpoints.dart';
import 'api_response.dart';

/// Read/write access to Step 4 master data (FR-021 … FR-047).
///
/// ONE PLACE THAT KNOWS THE ENDPOINTS, SHARED BY EVERY SURFACE.
/// A screen asks this repository for a typed result; it never builds a path and
/// never decodes an envelope. That keeps the exhaustive `ApiEndpoints` list
/// meaningful as a scope control — a screen cannot reach a path that is not
/// declared there — and it means the Console and the Ops counter cannot drift
/// into two different ideas of what an endpoint returns.
///
/// It lives in this package rather than in one of the apps precisely so there is
/// only ONE of it. A second copy inside a second surface is how the two would
/// diverge on the very thing that must not diverge: which version token is sent,
/// and which field name carries an outlet id (Rule 34 hard rule 15).
///
/// EVERY WRITE CARRIES THE VERSION THE CALLER READ.
/// The `expectedVersion` argument is threaded through to the
/// `If-Unmodified-Since-Version` header, so an edit is refused rather than
/// silently overwriting somebody else's (threat T-12). A repository method that
/// quietly dropped it would turn every management screen into last-write-wins.
///
/// THE VERSION IS AN OPAQUE SERVER TOKEN, NOT A TIMESTAMP.
/// It is deliberately NOT `updated_at`: a second-precision timestamp cannot
/// distinguish two edits inside the same second, so a timestamp-based
/// precondition silently permits exactly the overwrite it was meant to stop.
/// The client compares and returns the token; it never parses or generates one.
///
/// NO CLIENT-SIDE TENANT FILTERING ANYWHERE IN THIS FILE.
/// Every response is already scoped by the server to the caller's verified
/// tenant. A client-side `where(tenantId == ...)` would imply the server might
/// return foreign rows, which is exactly the assumption Rule 02 forbids — and
/// would quietly become the only thing standing between tenants if it were ever
/// relied upon.
final class MasterDataRepository {
  const MasterDataRepository(this._client);

  final ApiClient _client;

  /// The largest page this client will ever ask for.
  ///
  /// Bounded on the client as well as the server (the server clamps to 100)
  /// because an unbounded list request is how a counter app on a cheap phone
  /// ends up holding a tenant's entire customer database in memory, and how a
  /// slow query becomes a denial of service against the outlet that is busy.
  static const int maxPerPage = 100;

  /// The page size the counter uses for a lookup.
  ///
  /// Small on purpose. The counter workflow is "find this one customer", not
  /// "browse everybody": a cashier types enough of a name or code to narrow it,
  /// and a short page keeps the first result on screen without a scroll.
  static const int counterPageSize = 20;

  // ------------------------------------------------------------------
  // Customers (FR-021 … FR-030)
  // ------------------------------------------------------------------

  Future<Result<List<CustomerSummary>>> customers({
    String? query,
    String? status,
    int perPage = 25,
  }) async {
    final result = await _client.get(
      ApiEndpoints.customers,
      query: <String, Object?>{
        if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
        'status': ?status,
        'per_page': _boundedPerPage(perPage),
      },
    );

    return result.map(
      (ApiSuccess success) =>
          _list(success, 'customers', CustomerSummary.fromJson),
    );
  }

  /// The full detail projection: email, staff-facing note, and addresses.
  ///
  /// A separate call from [customers] rather than a richer list, because the
  /// list projection deliberately carries no address at all (Rule 32 hard
  /// rule 4). Widening the list to avoid a second request would put an address
  /// in a list row, which is the thing that rule forbids.
  Future<Result<CustomerDetail>> customer(String id) async {
    final result = await _client.get(ApiEndpoints.customer(id));

    return result.map(
      (ApiSuccess success) =>
          CustomerDetail.fromJson(_object(success, 'customer')),
    );
  }

  Future<Result<CustomerDetail>> createCustomer({
    required String name,
    required String phone,
    String? email,
    String? internalNotes,
  }) async {
    final result = await _client.post(
      ApiEndpoints.customers,
      body: <String, Object?>{
        'name': name,
        'phone': phone,
        if (email != null && email.isNotEmpty) 'email': email,
        if (internalNotes != null && internalNotes.isNotEmpty)
          'internal_notes': internalNotes,
      },
    );

    return result.map(
      (ApiSuccess success) =>
          CustomerDetail.fromJson(_object(success, 'customer')),
    );
  }

  Future<Result<CustomerDetail>> updateCustomer({
    required String id,
    required String? expectedVersion,
    required Map<String, Object?> changes,
  }) async {
    final result = await _client.patch(
      ApiEndpoints.customer(id),
      body: changes,
      expectedVersion: expectedVersion,
    );

    return result.map(
      (ApiSuccess success) =>
          CustomerDetail.fromJson(_object(success, 'customer')),
    );
  }

  /// Archive, never delete.
  ///
  /// There is no destroy endpoint to call: a customer a future order references
  /// must stay resolvable, so archival replaces deletion (threat T-18).
  Future<Result<CustomerSummary>> archiveCustomer(String id) async {
    final result = await _client.post(ApiEndpoints.customerArchive(id));

    return result.map(
      (ApiSuccess success) =>
          CustomerSummary.fromJson(_object(success, 'customer')),
    );
  }

  // ------------------------------------------------------------------
  // Consent (FR-027, FR-028) — read and APPEND only
  // ------------------------------------------------------------------

  Future<Result<ConsentLedger>> consents(String customerId) async {
    final result = await _client.get(ApiEndpoints.customerConsents(customerId));

    return result.map((ApiSuccess success) {
      final data = success.dataAsMap;
      return ConsentLedger.fromJson(data);
    });
  }

  /// Record a consent decision.
  ///
  /// There is no `updateConsent` and no `deleteConsent` on this repository,
  /// because there is no such endpoint and there must never be one: a
  /// withdrawal is a NEW record appended to the history, never an edit of the
  /// record that granted (invariant C5). The history IS the evidence.
  ///
  /// `recordedAt` is not a parameter. A client-suppliable consent timestamp is a
  /// backdated consent record (threat T-07); the server stamps it.
  Future<Result<ConsentRecord>> recordConsent({
    required String customerId,
    required ConsentType type,
    required ConsentState state,
    required ConsentSource source,
    String? note,
  }) async {
    final result = await _client.post(
      ApiEndpoints.customerConsents(customerId),
      body: <String, Object?>{
        'consent_type': type.wireValue,
        'state': state.wireValue,
        'source': source.wireValue,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      },
    );

    return result.map(
      (ApiSuccess success) =>
          ConsentRecord.fromJson(_object(success, 'consent')),
    );
  }

  // ------------------------------------------------------------------
  // Service catalogue (FR-031 … FR-033)
  // ------------------------------------------------------------------

  Future<Result<List<CatalogService>>> services({
    String? query,
    int perPage = maxPerPage,
  }) async {
    final result = await _client.get(
      ApiEndpoints.services,
      query: <String, Object?>{
        if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
        'per_page': _boundedPerPage(perPage),
      },
    );

    return result.map(
      (ApiSuccess success) =>
          _list(success, 'services', CatalogService.fromJson),
    );
  }

  Future<Result<CatalogService>> createService({
    required String code,
    required String name,
    required ServiceUnitKind unitKind,
    int? minimumQuantity,
  }) async {
    final result = await _client.post(
      ApiEndpoints.services,
      body: <String, Object?>{
        'code': code,
        'name': name,
        'unit_kind': unitKind.wireValue,
        'minimum_quantity': ?minimumQuantity,
      },
    );

    return result.map(
      (ApiSuccess success) =>
          CatalogService.fromJson(_object(success, 'service')),
    );
  }

  Future<Result<CatalogService>> updateService({
    required String id,
    required String? expectedVersion,
    required Map<String, Object?> changes,
  }) async {
    final result = await _client.patch(
      ApiEndpoints.service(id),
      body: changes,
      expectedVersion: expectedVersion,
    );

    return result.map(
      (ApiSuccess success) =>
          CatalogService.fromJson(_object(success, 'service')),
    );
  }

  Future<Result<List<CatalogAddon>>> addons() async {
    final result = await _client.get(
      ApiEndpoints.serviceAddons,
      query: const <String, Object?>{'per_page': maxPerPage},
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'addons', CatalogAddon.fromJson),
    );
  }

  Future<Result<List<CatalogPackage>>> packages() async {
    final result = await _client.get(
      ApiEndpoints.servicePackages,
      query: const <String, Object?>{'per_page': maxPerPage},
    );

    return result.map(
      (ApiSuccess success) =>
          _list(success, 'packages', CatalogPackage.fromJson),
    );
  }

  // ------------------------------------------------------------------
  // Price lists (FR-034 … FR-040)
  // ------------------------------------------------------------------

  Future<Result<List<PriceListSummary>>> priceLists({String? status}) async {
    final result = await _client.get(
      ApiEndpoints.priceLists,
      query: <String, Object?>{'status': ?status, 'per_page': maxPerPage},
    );

    return result.map(
      (ApiSuccess success) =>
          _list(success, 'price_lists', PriceListSummary.fromJson),
    );
  }

  Future<Result<PriceListSummary>> priceList(String id) async {
    final result = await _client.get(ApiEndpoints.priceList(id));

    return result.map(
      (ApiSuccess success) =>
          PriceListSummary.fromJson(_object(success, 'price_list')),
    );
  }

  /// Publishing is irreversible and carries its own permission.
  ///
  /// A surface asks for a distinct, deliberate confirmation before calling this;
  /// the server checks `price_list.publish` regardless of what the surface did
  /// (Rule 32 hard rule 15, Rule 40 hard rule 2).
  Future<Result<PriceListSummary>> publishPriceList({
    required String id,
    String? supersedesId,
  }) async {
    final result = await _client.post(
      ApiEndpoints.priceListPublish(id),
      body: <String, Object?>{'supersedes_price_list_id': ?supersedesId},
    );

    return result.map(
      (ApiSuccess success) =>
          PriceListSummary.fromJson(_object(success, 'price_list')),
    );
  }

  /// Add a priced line to a DRAFT list.
  ///
  /// [amount] is a [Rupiah], not an `int` and never a `double`: the type is what
  /// stops a quantity being sent where a price belongs, and there is no
  /// floating-point value anywhere on this path (Rule 04 hard rule 2).
  Future<Result<PriceListEntry>> addPriceListItem({
    required String priceListId,
    required String serviceId,
    required Rupiah amount,
  }) async {
    final result = await _client.post(
      ApiEndpoints.priceListItems(priceListId),
      body: <String, Object?>{
        'service_id': serviceId,
        'amount_rupiah': amount.amount,
      },
    );

    return result.map(
      (ApiSuccess success) => PriceListEntry.fromJson(_object(success, 'item')),
    );
  }

  // ------------------------------------------------------------------
  // Outlet master data (FR-041 … FR-047)
  // ------------------------------------------------------------------

  Future<Result<OutletMasterData>> outletMasterData(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletMasterData(outletId));

    return result.map(
      (ApiSuccess success) =>
          OutletMasterData.fromJson(_object(success, 'outlet')),
    );
  }

  Future<Result<OutletMasterData>> updateOutletMasterData({
    required String outletId,
    required String? expectedVersion,
    required Map<String, Object?> changes,
  }) async {
    final result = await _client.patch(
      ApiEndpoints.outletMasterData(outletId),
      body: changes,
      expectedVersion: expectedVersion,
    );

    return result.map(
      (ApiSuccess success) =>
          OutletMasterData.fromJson(_object(success, 'outlet')),
    );
  }

  Future<Result<List<OutletServiceZone>>> serviceZones(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletServiceZones(outletId));

    return result.map(
      (ApiSuccess success) =>
          _list(success, 'zones', OutletServiceZone.fromJson),
    );
  }

  Future<Result<List<OutletShift>>> shifts(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletShifts(outletId));

    return result.map(
      (ApiSuccess success) => _list(success, 'shifts', OutletShift.fromJson),
    );
  }

  /// Printer CONFIGURATION (FR-045).
  ///
  /// What a printer prints is FR-052 in Step 5. This reads and writes the
  /// device's configuration and nothing about a document (DEC-0030).
  Future<Result<List<OutletPrinter>>> printers(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletPrinters(outletId));

    return result.map(
      (ApiSuccess success) =>
          _list(success, 'printers', OutletPrinter.fromJson),
    );
  }

  // ------------------------------------------------------------------
  // Staff assignment (ROADMAP Step 4 scope, FR-018)
  // ------------------------------------------------------------------

  Future<Result<List<StaffMember>>> staff({
    String? status,
    int perPage = maxPerPage,
  }) async {
    final result = await _client.get(
      ApiEndpoints.staff,
      query: <String, Object?>{
        'status': ?status,
        'per_page': _boundedPerPage(perPage),
      },
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'staff', StaffMember.fromJson),
    );
  }

  Future<Result<StaffMember>> staffMember(String membershipId) async {
    final result = await _client.get(ApiEndpoints.staffMember(membershipId));

    return result.map(
      (ApiSuccess success) => StaffMember.fromJson(_object(success, 'staff')),
    );
  }

  /// Roster a membership onto an outlet.
  ///
  /// The field is `assigned_outlet_id`, NOT `outlet_id`, and the distinction is
  /// load-bearing rather than stylistic. Step 3's tenant middleware treats a
  /// request-body `outlet_id` as the CALLER'S ACTIVE OUTLET selector, so using
  /// that name here would silently switch the operator's own working context
  /// every time they edited the roster — and would make a cross-tenant attempt
  /// fail in middleware, which is a refusal arrived at for the wrong reason and
  /// one that would stop being true the moment the middleware changed.
  ///
  /// Assigning an outlet says WHERE somebody works and confers no capability
  /// whatsoever. Conferring capability is [assignRole], which is a different
  /// endpoint behind a different permission on purpose: one endpoint doing both
  /// would make the roster screen a privilege-escalation path.
  Future<Result<OutletAssignment>> assignOutlet({
    required String membershipId,
    required String outletId,
  }) async {
    final result = await _client.post(
      ApiEndpoints.staffOutlets(membershipId),
      body: <String, Object?>{'assigned_outlet_id': outletId},
    );

    return result.map(
      (ApiSuccess success) =>
          OutletAssignment.fromJson(_object(success, 'assignment')),
    );
  }

  /// Revoke an outlet assignment.
  ///
  /// A POST rather than a DELETE because it RECORDS a revocation — who, when —
  /// rather than removing the row, so the roster history a later audit needs
  /// survives.
  Future<Result<OutletAssignment>> revokeOutletAssignment({
    required String membershipId,
    required String assignmentId,
  }) async {
    final result = await _client.post(
      ApiEndpoints.staffOutletRevoke(membershipId, assignmentId),
    );

    return result.map(
      (ApiSuccess success) =>
          OutletAssignment.fromJson(_object(success, 'assignment')),
    );
  }

  /// Grant a tenant role to a membership.
  ///
  /// [role] is a [TenantRole], not a `String`. The type is the control: a
  /// surface cannot post a role key that this build does not enumerate, and the
  /// enumeration contains no platform role at all (DEC-0025 §8).
  ///
  /// It is NOT the authorization decision. The server re-checks the permission
  /// AND applies the escalation guard, refusing any role that carries a
  /// permission the caller does not itself hold. A client that offered too much
  /// produces a refused request, never an unauthorized grant.
  Future<Result<StaffMember>> assignRole({
    required String membershipId,
    required TenantRole role,
  }) async {
    final result = await _client.post(
      ApiEndpoints.staffRoles(membershipId),
      body: <String, Object?>{'role': role.wireValue},
    );

    return result.map(
      (ApiSuccess success) => StaffMember.fromJson(_object(success, 'staff')),
    );
  }

  Future<Result<StaffMember>> removeRole({
    required String membershipId,
    required TenantRole role,
  }) async {
    final result = await _client.delete(
      ApiEndpoints.staffRole(membershipId, role.wireValue),
    );

    return result.map(
      (ApiSuccess success) => StaffMember.fromJson(_object(success, 'staff')),
    );
  }

  // ------------------------------------------------------------------
  // Decoding
  // ------------------------------------------------------------------

  /// Clamp a caller's page size into the range the server accepts.
  ///
  /// Clamped rather than rejected: a page size is a client detail, and failing a
  /// customer lookup because a caller asked for 500 rows would be a worse
  /// outcome than quietly asking for 100. The SERVER clamps too — this is the
  /// belt, not the braces.
  static int _boundedPerPage(int requested) =>
      requested < 1 ? 1 : (requested > maxPerPage ? maxPerPage : requested);

  /// Decode a single object under [key].
  ///
  /// Returns an empty map when the key is absent OR carries a shape this method
  /// did not expect — a list where an object belongs, say. The DOMAIN type's
  /// `fromJson` then fails loudly on its required fields, naming the field it
  /// could not find, rather than this method throwing an opaque cast error from
  /// inside a `Result.map`. A silently defaulted id is how a screen ends up
  /// editing the wrong row, so neither path invents one.
  ///
  /// A blind `as Map<String, Object?>?` would throw a `TypeError` whose message
  /// says nothing about which endpoint disagreed with which client.
  static Map<String, Object?> _object(ApiSuccess success, String key) {
    final raw = success.dataAsMap[key];
    return raw is Map<String, Object?> ? raw : const <String, Object?>{};
  }

  /// Decode a paginated collection under [key].
  ///
  /// An entry the client cannot parse is a SERVER/CLIENT MISMATCH and is
  /// allowed to throw rather than being skipped. Silently dropping it would show
  /// an operator a short list with no indication that anything was missing — and
  /// a master-data screen that quietly omits a row is worse than one that fails
  /// loudly.
  static List<T> _list<T>(
    ApiSuccess success,
    String key,
    T Function(Map<String, Object?>) parse,
  ) {
    final raw = success.dataAsMap[key];

    if (raw is! List) {
      return const <Never>[];
    }

    return raw.cast<Map<String, Object?>>().map(parse).toList(growable: false);
  }
}
