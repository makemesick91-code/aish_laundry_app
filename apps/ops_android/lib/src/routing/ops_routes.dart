/// Ops surface routes.
///
/// The tenant and outlet selection routes are FIRST-CLASS screens, not modal
/// conveniences. A staff member who works for two competing laundry businesses
/// must choose deliberately and must be able to see which one they are in
/// before they touch anything.
abstract final class OpsRoutes {
  static const String startup = '/';
  static const String signIn = '/masuk';
  static const String selectTenant = '/pilih-tenant';
  static const String selectOutlet = '/pilih-outlet';
  static const String home = '/beranda';

  static const String sessionExpired = '/sesi-berakhir';
  static const String sessionRevoked = '/sesi-dicabut';
  static const String deviceRevoked = '/perangkat-dicabut';
  static const String membershipSuspended = '/keanggotaan-ditangguhkan';
  static const String membershipRevoked = '/keanggotaan-dicabut';
  static const String outletInactive = '/outlet-nonaktif';
  static const String accessDenied = '/akses-ditolak';

  // -------------------------------------------------------------------------
  // STEP 4 — LAUNDRY MASTER DATA (FR-018, FR-021 … FR-047)
  //
  // These are MASTER-DATA routes. None of them names an order, a payment, a
  // document, a pickup or a delivery, because none of them reaches one: those
  // are Step 5 and later, and their placeholders are still placeholders below
  // (CLAUDE.md §3 — roadmap lock, DEC-0030).
  //
  // A customer is addressed by ID in the PATH. It is a tenant-scoped identifier
  // that grants nothing on its own — the server resolves it within the caller's
  // verified tenant and answers the same 404 for "belongs to another tenant" as
  // for "does not exist" (Rule 48 hard rule 5).
  // -------------------------------------------------------------------------
  static const String customers = '/beranda/pelanggan';
  static const String customerCreate = '/beranda/pelanggan/baru';

  /// The detail route pattern. `baru` above is matched FIRST by the router, so
  /// it can never be read as a customer id.
  static const String customerDetail = '/beranda/pelanggan/:customerId';

  static String customerDetailFor(String customerId) =>
      '/beranda/pelanggan/$customerId';

  static const String catalogue = '/beranda/layanan';

  /// Outlet master data. Deliberately carries NO outlet id: the screen operates
  /// on the session's active outlet only, so there is no parameter an operator
  /// could edit to address a different outlet.
  static const String outletMasterData = '/beranda/outlet';

  static const String staffRoster = '/beranda/staf';

  // Step 5 — POS counter (orders and payments), DEC-0035.
  static const String counter = '/beranda/kasir';
  static const String counterNewOrder = '/beranda/kasir/baru';

  /// The detail route pattern and its builder. Literal `baru` is declared before
  /// this `:orderId` pattern in the router, so `/kasir/baru` is not swallowed as
  /// an order id.
  static const String counterOrderDetail = '/beranda/kasir/:orderId';
  static String counterOrderDetailFor(String orderId) =>
      '/beranda/kasir/$orderId';

  // Future canonical Steps — placeholder only.
  static const String futureProduction = '/beranda/produksi';
  static const String futureQualityControl = '/beranda/kendali-mutu';
  static const String futureCourier = '/beranda/kurir';
  static const String futureReports = '/beranda/laporan';
}
