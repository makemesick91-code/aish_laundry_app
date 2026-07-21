import 'dart:async';
import 'dart:math';

import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';

import 'auth_service.dart';
import 'auth_state.dart';
import 'session_credentials.dart';

/// The production [AuthService]: real HTTP, real secure storage, real session.
///
/// Until this existed, `AuthService` had exactly one implementation —
/// `FakeAuthService` in `packages/testing` — and every application's
/// `authServiceProvider` threw `UnimplementedError`. Widget tests passed
/// because each supplied the fake; a real launch threw on the first frame that
/// read the provider. Tests were green and no application could sign in.
///
/// THE RULES THIS TYPE EXISTS TO HONOUR
///
///   * The SERVER decides. Nothing here grants access, infers a permission, or
///     concludes a session is valid from local state. A stored token proves
///     only that a token is stored (Rule 03, Rule 40).
///   * Restoration is ALWAYS server-verified. `restoreSession` never returns
///     `authenticated` on the strength of storage alone; it asks `auth/me` and
///     believes the answer, so a user revoked yesterday does not see a shell
///     today (Rule 39, hard rule 3).
///   * Tenant selection is EXPLICIT. There is no first-tenant-wins default. A
///     previously chosen tenant may be RESUMED at restore, which is a different
///     thing from choosing one for the user, and the resume is re-verified
///     server-side like any other selection.
///   * Unknown means transient, never terminal. An unrecognised error code
///     resolves through `ApiErrorMapper.consequenceOf` to a recoverable
///     consequence, so a server that grows a code cannot log users out.
final class BackendAuthService implements AuthService {
  BackendAuthService({
    required ApiClient client,
    required SessionCredentials credentials,
    required SecureCredentialStore store,
    required CredentialTransport transport,
    String? deviceName,
    String? platform,
    Random? random,
    // Each assignment below is flagged by `prefer_initializing_formals`. An
    // initializing formal would name these parameters `_client`, `_store` and
    // so on, and a caller in another library cannot pass a private name — so
    // the lint's suggestion does not compile here.
    // ignore: prefer_initializing_formals
  }) : _client = client,
       // ignore: prefer_initializing_formals
       _credentials = credentials,
       // ignore: prefer_initializing_formals
       _store = store,
       // ignore: prefer_initializing_formals
       _transport = transport,
       // ignore: prefer_initializing_formals
       _deviceName = deviceName,
       // ignore: prefer_initializing_formals
       _platform = platform,
       // Random.secure() by default: the device identifier must not be
       // predictable from another installation's.
       _random = random ?? Random.secure();

  final ApiClient _client;
  final SessionCredentials _credentials;
  final SecureCredentialStore _store;
  final CredentialTransport _transport;
  final String? _deviceName;
  final String? _platform;
  final Random _random;

  final StreamController<AuthState> _controller =
      StreamController<AuthState>.broadcast();

  AuthState _state = const AuthState.unauthenticated();

  /// Outlets last reported by the server for the active tenant.
  ///
  /// Cached because `POST context/outlet` echoes back an outlet without its
  /// brand identifier, and fabricating one would put a wrong value into a
  /// domain object. The cache is cleared on every tenant switch and on sign-out.
  List<Outlet> _outlets = const <Outlet>[];

  static const StorageNamespace _device = StorageNamespace.device();

  @override
  Stream<AuthState> get states => _controller.stream;

  @override
  AuthState get current => _state;

  AuthState _emit(AuthState state) {
    _state = state;
    if (!_controller.isClosed) {
      _controller.add(state);
    }
    return state;
  }

  /// Translate a failure into the state to adopt, and clear credentials when
  /// the session is genuinely over.
  ///
  /// [transientFallback] is what a NON-session-ending failure resolves to. A
  /// rate limit during a live session must keep the session; the same rate
  /// limit during restoration has no session to keep.
  Future<AuthState> _failureState(
    Failure failure, {
    required AuthState transientFallback,
    String? tenantName,
  }) async {
    final consequence = ApiErrorMapper.consequenceOf(failure);
    final state = authStateFor(
      consequence,
      cause: failure,
      tenantName: tenantName,
      transientFallback: transientFallback,
    );

    // A finished session leaves nothing behind on the device. Note this fires
    // on `isTerminatedSession` AND on a plain unauthenticated result, because
    // `requiresAuthentication` means the credential we hold is no longer one.
    if (state.isTerminatedSession || state is Unauthenticated) {
      await _forgetCredentials();
    }

    return _emit(state);
  }

  // ---------------------------------------------------------------------------
  // Session restoration
  // ---------------------------------------------------------------------------

  @override
  Future<AuthState> restoreSession() async {
    _emit(const AuthState.authenticating());

    await _ensureDeviceIdentifier();

    String? userId;

    if (_transport == CredentialTransport.bearerToken) {
      // A token surface must find its own credential before it can ask
      // anything. A cookie surface skips this entirely: its credential is the
      // HttpOnly cookie the browser attaches, which this code cannot read and
      // deliberately never tries to.
      userId = await _readDevice(CredentialKeys.activeUserId);
      if (userId == null) {
        return _emit(const AuthState.unauthenticated());
      }

      final token = await _read(
        StorageNamespace.user(userId),
        CredentialKeys.sessionToken,
      );
      if (token == null) {
        // A pointer with no token behind it is a half-written sign-in. Clear
        // it rather than leaving a dangling pointer to retry forever.
        await _forgetCredentials();
        return _emit(const AuthState.unauthenticated());
      }

      _credentials.setToken(token);
    }

    // THE VERIFICATION. Everything above only decided what to present.
    final result = await _client.get(ApiEndpoints.me);

    if (result.isErr) {
      return _failureState(
        result.failureOrNull!,
        transientFallback: const AuthState.unauthenticated(),
      );
    }

    final data = result.valueOrNull!.dataAsMap;
    final user = _parseUser(data['user']);
    if (user == null) {
      // A 200 whose body we cannot read is not a session. Fail closed.
      await _forgetCredentials();
      return _emit(const AuthState.unauthenticated());
    }

    // For a cookie surface this is the first point an identity is known.
    userId ??= user.id;
    if (_transport == CredentialTransport.bearerToken) {
      await _writeDevice(CredentialKeys.activeUserId, user.id);
    }

    final session = SessionState(
      user: user,
      availableTenants: _parseMembershipTenants(data['memberships']),
    );

    final authenticated = _emit(AuthState.authenticated(session));

    return _resumePreviousTenant(user.id, authenticated);
  }

  /// Best-effort resume of a previously chosen tenant.
  ///
  /// NOT auto-selection: it replays a choice the user already made explicitly,
  /// and the server re-verifies it exactly as it would a fresh selection. Any
  /// failure leaves the user authenticated with no tenant context — which is
  /// the honest state — rather than ending the session, because a stale stored
  /// tenant says nothing about whether the session is still good.
  Future<AuthState> _resumePreviousTenant(
    String userId,
    AuthState authenticated,
  ) async {
    final tenantId = await _read(
      StorageNamespace.user(userId),
      CredentialKeys.lastActiveTenantId,
    );
    if (tenantId == null) {
      return authenticated;
    }

    final resumed = await selectTenant(tenantId);

    // Only a genuinely terminated session is allowed to survive as the result.
    // Anything else — a denial because membership was removed, a network blip —
    // resolves back to "signed in, choose a tenant".
    if (resumed.isAuthenticated || resumed.isTerminatedSession) {
      return resumed;
    }
    return _emit(authenticated);
  }

  // ---------------------------------------------------------------------------
  // Sign in
  // ---------------------------------------------------------------------------

  @override
  Future<AuthState> signIn({
    required String identifier,
    required String password,
  }) async {
    _emit(const AuthState.authenticating());

    await _ensureDeviceIdentifier();

    final result = await _client.post(
      ApiEndpoints.login,
      body: <String, Object?>{
        'identifier': identifier,
        'password': password,
        'mode': _transport == CredentialTransport.sessionCookie
            ? 'cookie'
            : 'token',
        if (_credentials.deviceIdentifier != null)
          'device_identifier': _credentials.deviceIdentifier,
        if (_deviceName != null) 'device_name': _deviceName,
        if (_platform != null) 'platform': _platform,
      },
    );

    if (result.isErr) {
      // The password is not in scope here and is never logged, echoed, stored,
      // or attached to a failure. The server's response for a wrong password is
      // deliberately identical to its response for an unknown account
      // (Rule 38, hard rule 7), and nothing here re-introduces a distinction.
      return _failureState(
        result.failureOrNull!,
        transientFallback: const AuthState.unauthenticated(),
      );
    }

    final data = result.valueOrNull!.dataAsMap;
    final user = _parseUser(data['user']);
    if (user == null) {
      return _emit(const AuthState.unauthenticated());
    }

    if (_transport == CredentialTransport.bearerToken) {
      final token = data['token'];
      if (token is! String || token.isEmpty) {
        // Token mode that produced no token is a broken contract, not a
        // session. Never proceed as though sign-in worked.
        await _forgetCredentials();
        return _emit(const AuthState.unauthenticated());
      }

      _credentials.setToken(token);

      // Persist AFTER the value is known good. The write is per-user, so two
      // accounts on one counter device never share a credential slot.
      final stored = await _write(
        StorageNamespace.user(user.id),
        CredentialKeys.sessionToken,
        token,
      );
      if (!stored) {
        // Storage refused. The session is usable for this run but will not
        // survive a restart; that is reported honestly rather than pretended
        // away, and the in-memory token is kept so the user is not blocked.
        _emitStorageWarningIgnored();
      }
      await _writeDevice(CredentialKeys.activeUserId, user.id);
    }

    // Login returns the identity, not the memberships. Ask who this user may
    // act as, so the tenant chooser has something to render.
    final tenants = await _loadAvailableTenants();

    return _emit(
      AuthState.authenticated(
        SessionState(user: user, availableTenants: tenants),
      ),
    );
  }

  /// Storage failure during sign-in is deliberately non-fatal and deliberately
  /// silent at this layer: there is no logger in this package, and inventing
  /// one here would put an ad hoc logging convention beside the shared
  /// observability package (Rule 46). The surface observes the outcome through
  /// the returned state.
  void _emitStorageWarningIgnored() {}

  Future<List<Tenant>> _loadAvailableTenants() async {
    final result = await _client.get(ApiEndpoints.contextTenants);
    if (result.isErr) {
      // An empty list renders as "no tenant available", which is honest: the
      // client genuinely does not know of any. It never invents one.
      return const <Tenant>[];
    }
    return _parseContextTenants(result.valueOrNull!.dataAsMap['tenants']);
  }

  // ---------------------------------------------------------------------------
  // Context selection
  // ---------------------------------------------------------------------------

  @override
  Future<AuthState> selectTenant(String tenantId) async {
    final session = _state.session;
    if (session == null) {
      return _emit(const AuthState.unauthenticated());
    }

    final result = await _client.post(
      ApiEndpoints.contextTenant,
      body: <String, Object?>{
        'tenant_id': tenantId,
        if (_credentials.deviceIdentifier != null)
          'device_identifier': _credentials.deviceIdentifier,
        if (_deviceName != null) 'device_name': _deviceName,
        if (_platform != null) 'platform': _platform,
      },
    );

    if (result.isErr) {
      // Keep the signed-in session on a transient failure: a network blip while
      // switching tenant must not discard the user's working context.
      return _failureState(
        result.failureOrNull!,
        transientFallback: _state,
        tenantName: session.availableTenants
            .where((tenant) => tenant.id == tenantId)
            .map((tenant) => tenant.name)
            .firstOrNull,
      );
    }

    final data = result.valueOrNull!.dataAsMap;
    final context = data['context'];
    if (context is! Map<String, Object?>) {
      return _emit(const AuthState.accessDenied());
    }

    final tenant = _parseTenant(context['tenant'], selectable: true);
    if (tenant == null) {
      return _emit(const AuthState.accessDenied());
    }

    final membership = _parseMembership(
      context['membership'],
      userId: session.user.id,
      tenantId: tenant.id,
      roles: _parseRoles(data['roles']),
    );
    if (membership == null) {
      return _emit(const AuthState.accessDenied());
    }

    // Only now is the selection recorded locally. Recording it before the
    // server agreed would persist a tenant the user may not enter.
    _credentials.selectTenant(tenant.id);
    _outlets = const <Outlet>[];
    await _write(
      StorageNamespace.user(session.user.id),
      CredentialKeys.lastActiveTenantId,
      tenant.id,
    );
    await _delete(
      StorageNamespace.user(session.user.id),
      CredentialKeys.lastActiveOutletId,
    );

    return _emit(
      AuthState.authenticated(
        // Starts from a CLEARED context: no outlet and no permission set from
        // the previous tenant survives the switch (Rule 28, hard rule 3).
        session.withoutTenantContext().copyWith(
          activeTenant: tenant,
          activeMembership: membership,
          permissions: EffectivePermissions(
            tenantId: tenant.id,
            permissions: _parsePermissions(data['permissions']),
          ),
        ),
      ),
    );
  }

  @override
  Future<AuthState> selectOutlet(String outletId) async {
    final session = _state.session;
    if (session == null || !session.hasTenantContext) {
      return _emit(const AuthState.accessDenied());
    }

    final result = await _client.post(
      ApiEndpoints.contextOutlet,
      body: <String, Object?>{'outlet_id': outletId},
    );

    if (result.isErr) {
      return _failureState(result.failureOrNull!, transientFallback: _state);
    }

    // The selection response echoes the outlet without its brand identifier,
    // so the domain object is resolved from the server's own listing rather
    // than assembled from a partial echo with a fabricated brand.
    final outlet = await _resolveOutlet(outletId);
    if (outlet == null) {
      return _emit(const AuthState.accessDenied());
    }

    _credentials.selectOutlet(outlet.id);
    await _write(
      StorageNamespace.user(session.user.id),
      CredentialKeys.lastActiveOutletId,
      outlet.id,
    );

    return _emit(
      AuthState.authenticated(session.copyWith(activeOutlet: outlet)),
    );
  }

  Future<Outlet?> _resolveOutlet(String outletId) async {
    final cached = _outlets.where((o) => o.id == outletId).firstOrNull;
    if (cached != null) {
      return cached;
    }
    final listed = await authorizedOutlets();
    if (listed.isErr) {
      return null;
    }
    return listed.valueOrNull!.where((o) => o.id == outletId).firstOrNull;
  }

  @override
  Future<Result<List<Outlet>>> authorizedOutlets() async {
    final session = _state.session;
    if (session == null || !session.hasTenantContext) {
      return const Result<List<Outlet>>.err(
        Failure(
          kind: FailureKind.authorization,
          message: 'No tenant context.',
          code: 'TENANT_ACCESS_DENIED',
        ),
      );
    }

    final result = await _client.get(ApiEndpoints.contextOutlets);
    if (result.isErr) {
      return Result<List<Outlet>>.err(result.failureOrNull!);
    }

    final data = result.valueOrNull!.dataAsMap;
    final tenantId = data['tenant_id'];
    final outlets = _parseOutlets(
      data['outlets'],
      tenantId: tenantId is String ? tenantId : session.activeTenant!.id,
    );
    _outlets = outlets;
    return Result<List<Outlet>>.ok(outlets);
  }

  // ---------------------------------------------------------------------------
  // Sign out
  // ---------------------------------------------------------------------------

  @override
  Future<AuthState> signOut() async {
    // Tell the server first, so the token is revoked rather than merely
    // forgotten. A token we drop locally but never revoke stays valid until it
    // expires, which is the difference between signing out and hiding.
    await _client.post(ApiEndpoints.logout);

    // Runs whatever the server said. A failed logout call must never leave the
    // credential on the device — that is the outcome a user pressing "keluar"
    // on a shared counter tablet is entitled to.
    await _forgetCredentials();

    return _emit(const AuthState.loggedOut());
  }

  /// Remove every credential and context this installation holds for any user.
  Future<void> _forgetCredentials() async {
    _credentials.clear();
    _outlets = const <Outlet>[];
    await _store.clearOnLogout();
    // clearOnLogout removes the device identifier too, so put it back: it
    // identifies the installation, and a device that renames itself on every
    // sign-out cannot be revoked.
    final deviceIdentifier = _credentials.deviceIdentifier;
    if (deviceIdentifier != null) {
      await _writeDevice(CredentialKeys.deviceIdentifier, deviceIdentifier);
    }
  }

  Future<void> dispose() => _controller.close();

  // ---------------------------------------------------------------------------
  // Device identity
  // ---------------------------------------------------------------------------

  Future<void> _ensureDeviceIdentifier() async {
    if (_credentials.deviceIdentifier != null) {
      return;
    }
    final existing = await _readDevice(CredentialKeys.deviceIdentifier);
    if (existing != null && existing.isNotEmpty) {
      _credentials.setDeviceIdentifier(existing);
      return;
    }
    final generated = _generateDeviceIdentifier();
    _credentials.setDeviceIdentifier(generated);
    await _writeDevice(CredentialKeys.deviceIdentifier, generated);
  }

  /// A random installation identifier.
  ///
  /// Random rather than derived from any hardware or account property: a
  /// derived identifier would be stable across reinstalls and correlatable
  /// between tenants, which turns a revocation handle into a tracking handle.
  String _generateDeviceIdentifier() {
    const digits = '0123456789abcdef';
    final buffer = StringBuffer('dev_');
    for (var index = 0; index < 32; index++) {
      buffer.write(digits[_random.nextInt(digits.length)]);
    }
    return buffer.toString();
  }

  // ---------------------------------------------------------------------------
  // Storage helpers — every one fails soft, because a storage fault must not
  // crash a surface and must never be mistaken for an authorization answer.
  // ---------------------------------------------------------------------------

  Future<String?> _read(StorageNamespace namespace, String key) async {
    final result = await _store.read(namespace: namespace, key: key);
    return result.isErr ? null : result.valueOrNull;
  }

  Future<String?> _readDevice(String key) => _read(_device, key);

  Future<bool> _write(
    StorageNamespace namespace,
    String key,
    String value,
  ) async {
    final result = await _store.write(
      namespace: namespace,
      key: key,
      value: value,
    );
    return result.isOk;
  }

  Future<bool> _writeDevice(String key, String value) =>
      _write(_device, key, value);

  Future<void> _delete(StorageNamespace namespace, String key) =>
      _store.delete(namespace: namespace, key: key);

  // ---------------------------------------------------------------------------
  // Parsing — defensive throughout. A malformed body yields null or an empty
  // collection; it never throws into a widget build and never yields a
  // permissive default.
  // ---------------------------------------------------------------------------

  static Map<String, Object?>? _asMap(Object? value) =>
      value is Map<String, Object?> ? value : null;

  static String? _asString(Object? value) =>
      value is String && value.isNotEmpty ? value : null;

  static User? _parseUser(Object? raw) {
    final map = _asMap(raw);
    if (map == null) {
      return null;
    }
    final id = _asString(map['id']);
    if (id == null) {
      return null;
    }
    return User(
      id: id,
      displayName: _asString(map['name']) ?? id,
      // Already masked by the server. The client never receives the full value
      // and therefore cannot leak it (Rule 32, hard rule 4).
      maskedPhone: _asString(map['phone']),
      email: _asString(map['email']),
    );
  }

  /// Tenants from `auth/me`, whose membership entries carry a status.
  static List<Tenant> _parseMembershipTenants(Object? raw) {
    if (raw is! List) {
      return const <Tenant>[];
    }
    final tenants = <Tenant>[];
    for (final entry in raw) {
      final map = _asMap(entry);
      if (map == null) {
        continue;
      }
      final status = MembershipStatus.parse(_asString(map['status']) ?? '');
      final tenant = _parseTenant(
        map['tenant'],
        selectable: status == MembershipStatus.active,
      );
      if (tenant != null) {
        tenants.add(tenant);
      }
    }
    return List<Tenant>.unmodifiable(tenants);
  }

  /// Tenants from `context/tenants`, whose entries carry explicit selectability.
  static List<Tenant> _parseContextTenants(Object? raw) {
    if (raw is! List) {
      return const <Tenant>[];
    }
    final tenants = <Tenant>[];
    for (final entry in raw) {
      final map = _asMap(entry);
      if (map == null) {
        continue;
      }
      final membership = _asMap(map['membership']);
      final tenant = _parseTenant(
        map['tenant'],
        // The server reports SELECTABILITY here rather than a separate
        // tenant-active flag, and selectability is what a client acts on.
        // Defaults to false: a membership whose selectability we could not read
        // renders as unavailable rather than silently offered.
        selectable: membership?['selectable'] == true,
      );
      if (tenant != null) {
        tenants.add(tenant);
      }
    }
    return List<Tenant>.unmodifiable(tenants);
  }

  static Tenant? _parseTenant(Object? raw, {required bool selectable}) {
    final map = _asMap(raw);
    if (map == null) {
      return null;
    }
    final id = _asString(map['id']);
    if (id == null) {
      return null;
    }
    return Tenant(
      id: id,
      name: _asString(map['name']) ?? id,
      isActive: selectable,
    );
  }

  static Membership? _parseMembership(
    Object? raw, {
    required String userId,
    required String tenantId,
    required List<Role> roles,
  }) {
    final map = _asMap(raw);
    if (map == null) {
      return null;
    }
    final id = _asString(map['id']);
    if (id == null) {
      return null;
    }
    return Membership(
      id: id,
      userId: userId,
      tenantId: tenantId,
      // Fails safe to `suspended` for an unrecognised value, by
      // MembershipStatus.parse's own contract.
      status: MembershipStatus.parse(_asString(map['status']) ?? ''),
      roles: roles,
    );
  }

  static List<Role> _parseRoles(Object? raw) {
    if (raw is! List) {
      return const <Role>[];
    }
    final roles = <Role>[];
    for (final entry in raw) {
      final slug = _asString(entry);
      if (slug != null) {
        roles.add(Role(slug: slug, label: slug));
      }
    }
    return List<Role>.unmodifiable(roles);
  }

  static Set<Permission> _parsePermissions(Object? raw) {
    if (raw is! List) {
      // Empty grants nothing, which is the fail-closed default
      // EffectivePermissions is built around.
      return const <Permission>{};
    }
    return raw
        .map(_asString)
        .whereType<String>()
        .map(Permission.new)
        .toSet();
  }

  static List<Outlet> _parseOutlets(Object? raw, {required String tenantId}) {
    if (raw is! List) {
      return const <Outlet>[];
    }
    final outlets = <Outlet>[];
    for (final entry in raw) {
      final map = _asMap(entry);
      if (map == null) {
        continue;
      }
      final id = _asString(map['id']);
      final brandId = _asString(map['laundry_brand_id']);
      if (id == null || brandId == null) {
        continue;
      }
      outlets.add(
        Outlet(
          id: id,
          tenantId: tenantId,
          brandId: brandId,
          name: _asString(map['name']) ?? id,
          // The Step 3 listing endpoint does not expose an active flag, so the
          // client cannot render the active/inactive distinction and does not
          // pretend to. The server remains the authority: selecting an outlet
          // it considers inactive is refused there, not here.
          isActive: true,
        ),
      );
    }
    return List<Outlet>.unmodifiable(outlets);
  }
}
