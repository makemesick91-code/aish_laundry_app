/// Every route this surface can be at.
///
/// The Step 3 routes are real. The future-feature routes exist so the shell has
/// somewhere honest to send a user, and every one of them renders the literal
/// notice `NOT IMPLEMENTED — OWNED BY FUTURE CANONICAL STEP`. None of them
/// fetches anything, submits anything, or renders a sample datum.
abstract final class CustomerRoutes {
  // Step 3 — real.
  static const String startup = '/';
  static const String signIn = '/masuk';
  static const String home = '/beranda';
  static const String sessionExpired = '/sesi-berakhir';
  static const String sessionRevoked = '/sesi-dicabut';
  static const String deviceRevoked = '/perangkat-dicabut';
  static const String accessDenied = '/akses-ditolak';
  static const String networkUnavailable = '/jaringan-tidak-tersedia';
  static const String serviceUnavailable = '/layanan-tidak-tersedia';
  static const String designSmoke = '/pratinjau-design-system';

  // Future canonical Steps — placeholder only.
  static const String futureOrders = '/beranda/pesanan';
  static const String futureTracking = '/beranda/lacak';
  static const String futurePickup = '/beranda/penjemputan';
  static const String futureInvoices = '/beranda/tagihan';
}
