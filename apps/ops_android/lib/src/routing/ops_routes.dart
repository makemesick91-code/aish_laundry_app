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

  // Future canonical Steps — placeholder only.
  static const String futureCounter = '/beranda/kasir';
  static const String futureProduction = '/beranda/produksi';
  static const String futureQualityControl = '/beranda/kendali-mutu';
  static const String futureCourier = '/beranda/kurir';
  static const String futureReports = '/beranda/laporan';
}
