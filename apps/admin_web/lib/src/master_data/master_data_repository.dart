import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';

/// The console's read/write access to Step 4 master data (FR-021 … FR-047).
///
/// ONE PLACE THAT KNOWS THE ENDPOINTS. A screen asks this repository for a
/// typed result; it never builds a path, and it never decodes an envelope. That
/// keeps the exhaustive `ApiEndpoints` list meaningful as a scope control — a
/// screen cannot reach a path that is not declared there.
///
/// EVERY WRITE CARRIES THE VERSION THE CALLER READ.
/// The `expectedVersion` argument is threaded through to the
/// `If-Unmodified-Since-Version` header, so a console edit is refused rather
/// than silently overwriting somebody else's (threat T-12). A repository method
/// that quietly dropped it would turn every management screen into
/// last-write-wins.
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

  // ------------------------------------------------------------------
  // Customers (FR-021 … FR-026)
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
        'per_page': perPage,
      },
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'customers', CustomerSummary.fromJson),
    );
  }

  Future<Result<CustomerSummary>> createCustomer({
    required String name,
    required String phone,
    String? email,
  }) async {
    final result = await _client.post(
      ApiEndpoints.customers,
      body: <String, Object?>{
        'name': name,
        'phone': phone,
        if (email != null && email.isNotEmpty) 'email': email,
      },
    );

    return result.map(
      (ApiSuccess success) => CustomerSummary.fromJson(
        (success.dataAsMap['customer'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  Future<Result<CustomerSummary>> updateCustomer({
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
      (ApiSuccess success) => CustomerSummary.fromJson(
        (success.dataAsMap['customer'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  // ------------------------------------------------------------------
  // Service catalogue (FR-031 … FR-033)
  // ------------------------------------------------------------------

  Future<Result<List<CatalogService>>> services({String? query}) async {
    final result = await _client.get(
      ApiEndpoints.services,
      query: <String, Object?>{
        if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
        'per_page': 100,
      },
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'services', CatalogService.fromJson),
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
      (ApiSuccess success) => CatalogService.fromJson(
        (success.dataAsMap['service'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
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
      (ApiSuccess success) => CatalogService.fromJson(
        (success.dataAsMap['service'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  Future<Result<List<CatalogAddon>>> addons() async {
    final result = await _client.get(
      ApiEndpoints.serviceAddons,
      query: const <String, Object?>{'per_page': 100},
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'addons', CatalogAddon.fromJson),
    );
  }

  Future<Result<List<CatalogPackage>>> packages() async {
    final result = await _client.get(
      ApiEndpoints.servicePackages,
      query: const <String, Object?>{'per_page': 100},
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'packages', CatalogPackage.fromJson),
    );
  }

  // ------------------------------------------------------------------
  // Price lists (FR-034 … FR-040)
  // ------------------------------------------------------------------

  Future<Result<List<PriceListSummary>>> priceLists({String? status}) async {
    final result = await _client.get(
      ApiEndpoints.priceLists,
      query: <String, Object?>{
        'status': ?status,
        'per_page': 100,
      },
    );

    return result.map(
      (ApiSuccess success) =>
          _list(success, 'price_lists', PriceListSummary.fromJson),
    );
  }

  Future<Result<PriceListSummary>> priceList(String id) async {
    final result = await _client.get(ApiEndpoints.priceList(id));

    return result.map(
      (ApiSuccess success) => PriceListSummary.fromJson(
        (success.dataAsMap['price_list'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  /// Publishing is irreversible and carries its own permission.
  ///
  /// The console asks for a distinct, deliberate confirmation before calling
  /// this; the server checks `price_list.publish` regardless of what the
  /// console did (Rule 32 hard rule 15, Rule 40 hard rule 2).
  Future<Result<PriceListSummary>> publishPriceList({
    required String id,
    String? supersedesId,
  }) async {
    final result = await _client.post(
      ApiEndpoints.priceListPublish(id),
      body: <String, Object?>{
        'supersedes_price_list_id': ?supersedesId,
      },
    );

    return result.map(
      (ApiSuccess success) => PriceListSummary.fromJson(
        (success.dataAsMap['price_list'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
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
      (ApiSuccess success) => PriceListEntry.fromJson(
        (success.dataAsMap['item'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  // ------------------------------------------------------------------
  // Outlet master data (FR-041 … FR-047)
  // ------------------------------------------------------------------

  Future<Result<OutletMasterData>> outletMasterData(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletMasterData(outletId));

    return result.map(
      (ApiSuccess success) => OutletMasterData.fromJson(
        (success.dataAsMap['outlet'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
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
      (ApiSuccess success) => OutletMasterData.fromJson(
        (success.dataAsMap['outlet'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  Future<Result<List<OutletServiceZone>>> serviceZones(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletServiceZones(outletId));

    return result.map(
      (ApiSuccess success) => _list(success, 'zones', OutletServiceZone.fromJson),
    );
  }

  Future<Result<List<OutletShift>>> shifts(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletShifts(outletId));

    return result.map(
      (ApiSuccess success) => _list(success, 'shifts', OutletShift.fromJson),
    );
  }

  Future<Result<List<OutletPrinter>>> printers(String outletId) async {
    final result = await _client.get(ApiEndpoints.outletPrinters(outletId));

    return result.map(
      (ApiSuccess success) => _list(success, 'printers', OutletPrinter.fromJson),
    );
  }

  // ------------------------------------------------------------------
  // Staff assignment (ROADMAP Step 4 scope, FR-018)
  // ------------------------------------------------------------------

  Future<Result<List<StaffMember>>> staff() async {
    final result = await _client.get(
      ApiEndpoints.staff,
      query: const <String, Object?>{'per_page': 100},
    );

    return result.map(
      (ApiSuccess success) => _list(success, 'staff', StaffMember.fromJson),
    );
  }

  /// Roster a membership onto an outlet.
  ///
  /// The field is `assigned_outlet_id`, NOT `outlet_id`: the server's tenant
  /// middleware treats a body `outlet_id` as the caller's ACTIVE OUTLET
  /// selector, so using that name would silently switch the console user's own
  /// working context every time they edited the roster.
  Future<Result<OutletAssignment>> assignOutlet({
    required String membershipId,
    required String outletId,
  }) async {
    final result = await _client.post(
      ApiEndpoints.staffOutlets(membershipId),
      body: <String, Object?>{'assigned_outlet_id': outletId},
    );

    return result.map(
      (ApiSuccess success) => OutletAssignment.fromJson(
        (success.dataAsMap['assignment'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  Future<Result<OutletAssignment>> revokeOutletAssignment({
    required String membershipId,
    required String assignmentId,
  }) async {
    final result = await _client.post(
      ApiEndpoints.staffOutletRevoke(membershipId, assignmentId),
    );

    return result.map(
      (ApiSuccess success) => OutletAssignment.fromJson(
        (success.dataAsMap['assignment'] as Map<String, Object?>?) ??
            const <String, Object?>{},
      ),
    );
  }

  // ------------------------------------------------------------------
  // Decoding
  // ------------------------------------------------------------------

  /// Decode a paginated collection under [key].
  ///
  /// An entry the client cannot parse is a SERVER/CLIENT MISMATCH and is
  /// allowed to throw rather than being skipped. Silently dropping it would
  /// show an operator a short list with no indication that anything was
  /// missing — and a master-data screen that quietly omits a row is worse than
  /// one that fails loudly.
  static List<T> _list<T>(
    ApiSuccess success,
    String key,
    T Function(Map<String, Object?>) parse,
  ) {
    final raw = success.dataAsMap[key];

    if (raw is! List) {
      return const <Never>[];
    }

    return raw
        .cast<Map<String, Object?>>()
        .map(parse)
        .toList(growable: false);
  }
}
