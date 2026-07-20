/// Console Web routes.
///
/// Paths are stable and bookmarkable, because a browser user WILL refresh and
/// WILL bookmark. Restoration after a refresh is a first-class requirement here
/// in a way it is not on the Android surfaces.
abstract final class ConsoleRoutes {
  static const String startup = '/';
  static const String signIn = '/masuk';
  static const String selectTenant = '/pilih-tenant';
  static const String portfolio = '/portofolio';

  static const String sessionExpired = '/sesi-berakhir';
  static const String membershipSuspended = '/keanggotaan-ditangguhkan';
  static const String membershipRevoked = '/keanggotaan-dicabut';
  static const String tenantAccessDenied = '/akses-tenant-ditolak';

  // Future canonical Steps — placeholder only.
  static const String futureMasterData = '/portofolio/data-induk';
  static const String futureFinance = '/portofolio/keuangan';
  static const String futureSubscription = '/portofolio/langganan';
  static const String futureAudit = '/portofolio/audit';
}
