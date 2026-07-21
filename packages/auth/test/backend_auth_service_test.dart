import 'dart:convert';

import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Every identifier below is fictional and recognisably so (Rule 45).
const String kUserId = 'usr_fiktif_00000001';
const String kTenantId = 'tnt_fiktif_00000001';
const String kOutletId = 'otl_fiktif_00000001';
const String kBrandId = 'brd_fiktif_00000001';
const String kMembershipId = 'mbr_fiktif_00000001';
const String kToken = 'token_fiktif_tidak_pernah_nyata';
const String kPassword = 'kata-sandi-fiktif-12345';

Environment env() => Environment.validate(
  environmentName: 'development',
  apiBaseUrl: 'http://127.0.0.1:8000/api/v1',
  appName: 'Uji',
).valueOrNull!;

/// A scripted backend keyed by request path.
class ScriptedBackend implements HttpClientAdapter {
  final Map<String, List<(int, Object?)>> _routes =
      <String, List<(int, Object?)>>{};
  final List<RequestOptions> requests = <RequestOptions>[];

  /// Queue a reply for [path]. Repeated calls queue successive replies, so a
  /// test can script "fails once, then succeeds".
  void on(String path, int status, Object? body) =>
      _routes.putIfAbsent(path, () => <(int, Object?)>[]).add((status, body));

  DioException? throwOn;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    if (throwOn != null) {
      throw throwOn!;
    }
    final queued = _routes[options.path];
    if (queued == null || queued.isEmpty) {
      return ResponseBody.fromString(
        jsonEncode(<String, Object?>{
          'error': <String, Object?>{'code': 'NOT_FOUND'},
          'meta': <String, Object?>{'request_id': 'req_fiktif'},
        }),
        404,
        headers: _json,
      );
    }
    final (status, body) = queued.length == 1 ? queued.first : queued.removeAt(0);
    return ResponseBody.fromString(
      jsonEncode(body ?? <String, Object?>{}),
      status,
      headers: _json,
    );
  }

  static const Map<String, List<String>> _json = <String, List<String>>{
    Headers.contentTypeHeader: <String>[Headers.jsonContentType],
  };

  @override
  void close({bool force = false}) {}
}

Map<String, Object?> ok(Object? data) => <String, Object?>{
  'data': data,
  'meta': <String, Object?>{'request_id': 'req_fiktif_00000001'},
};

Map<String, Object?> err(String code) => <String, Object?>{
  'error': <String, Object?>{'code': code, 'message': 'fiktif'},
  'meta': <String, Object?>{'request_id': 'req_fiktif_00000001'},
};

Map<String, Object?> get userPayload => <String, Object?>{
  'id': kUserId,
  'name': 'Bu Rina (fiktif)',
  'email': 'r***@contoh-fiktif.id',
};

/// Harness binding a scripted backend to a real service over a real ApiClient.
final class Harness {
  Harness({CredentialTransport transport = CredentialTransport.bearerToken})
    : backend = ScriptedBackend(),
      store = InMemoryCredentialStore(),
      credentials = SessionCredentials() {
    client = ApiClient(
      environment: env(),
      transport: transport,
      bearerToken: credentials.token,
      requestContext: credentials.context,
      dio: Dio()..httpClientAdapter = backend,
    );
    service = BackendAuthService(
      client: client,
      credentials: credentials,
      store: store,
      transport: transport,
    );
  }

  final ScriptedBackend backend;
  final InMemoryCredentialStore store;
  final SessionCredentials credentials;
  late final ApiClient client;
  late final BackendAuthService service;

  /// Put a signed-in credential on the device, as a previous run would have.
  Future<void> seedStoredSession() async {
    await store.write(
      namespace: const StorageNamespace.device(),
      key: CredentialKeys.activeUserId,
      value: kUserId,
    );
    await store.write(
      namespace: StorageNamespace.user(kUserId),
      key: CredentialKeys.sessionToken,
      value: kToken,
    );
  }

  String? get storedToken => store.keys
      .where((key) => key.endsWith(CredentialKeys.sessionToken))
      .firstOrNull;

  Future<void> dispose() => service.dispose();
}

void main() {
  group('signIn', () {
    test('a valid sign-in yields an authenticated session', () async {
      final h = Harness();
      addTearDown(h.dispose);
      h.backend.on(
        ApiEndpoints.login,
        200,
        ok(<String, Object?>{
          'user': userPayload,
          'mode': 'token',
          'token': kToken,
        }),
      );
      h.backend.on(
        ApiEndpoints.contextTenants,
        200,
        ok(<String, Object?>{
          'tenants': <Object?>[
            <String, Object?>{
              'tenant': <String, Object?>{
                'id': kTenantId,
                'name': 'Laundry Melati (fiktif)',
              },
              'membership': <String, Object?>{
                'id': kMembershipId,
                'status': 'active',
                'selectable': true,
              },
            },
          ],
        }),
      );

      final state = await h.service.signIn(
        identifier: 'rina',
        password: kPassword,
      );

      expect(state.isAuthenticated, isTrue);
      expect(state.session!.user.id, kUserId);
      expect(state.session!.availableTenants.single.id, kTenantId);
      // No tenant is selected FOR the user. There is no first-tenant-wins
      // default even when exactly one tenant exists.
      expect(state.session!.requiresTenantSelection, isTrue);
      expect(state.session!.hasTenantContext, isFalse);
    });

    test('the token is persisted per user, never device-wide', () async {
      final h = Harness();
      addTearDown(h.dispose);
      h.backend
        ..on(
          ApiEndpoints.login,
          200,
          ok(<String, Object?>{'user': userPayload, 'token': kToken}),
        )
        ..on(
          ApiEndpoints.contextTenants,
          200,
          ok(<String, Object?>{'tenants': <Object?>[]}),
        );

      await h.service.signIn(identifier: 'rina', password: kPassword);

      expect(h.storedToken, 'user:$kUserId/${CredentialKeys.sessionToken}');
    });

    test('the password is never stored anywhere on the device', () async {
      final h = Harness();
      addTearDown(h.dispose);
      h.backend
        ..on(
          ApiEndpoints.login,
          200,
          ok(<String, Object?>{'user': userPayload, 'token': kToken}),
        )
        ..on(
          ApiEndpoints.contextTenants,
          200,
          ok(<String, Object?>{'tenants': <Object?>[]}),
        );

      await h.service.signIn(identifier: 'rina', password: kPassword);

      for (final key in h.store.keys) {
        final value = await h.store.read(
          namespace: const StorageNamespace.device(),
          key: key,
        );
        expect(value.valueOrNull, isNot(kPassword));
      }
      expect(h.credentials.toString(), isNot(contains(kPassword)));
      expect(h.credentials.toString(), isNot(contains(kToken)));
    });

    test('invalid credentials leave nothing behind', () async {
      final h = Harness();
      addTearDown(h.dispose);
      h.backend.on(ApiEndpoints.login, 401, err('UNAUTHENTICATED'));

      final state = await h.service.signIn(
        identifier: 'rina',
        password: 'salah-fiktif',
      );

      expect(state, const AuthState.unauthenticated());
      expect(h.storedToken, isNull);
    });

    test(
      'token mode that returns no token is refused, not treated as success',
      () async {
        final h = Harness();
        addTearDown(h.dispose);
        // A 200 with no token is a broken server contract. Proceeding would
        // produce an "authenticated" shell holding no credential, and every
        // subsequent request would 401 with no explanation the user can act on.
        h.backend.on(
          ApiEndpoints.login,
          200,
          ok(<String, Object?>{'user': userPayload, 'mode': 'token'}),
        );

        final state = await h.service.signIn(
          identifier: 'rina',
          password: kPassword,
        );

        expect(state, const AuthState.unauthenticated());
        expect(h.storedToken, isNull);
      },
    );

    test('a cookie surface sends mode=cookie and stores no token', () async {
      final h = Harness(transport: CredentialTransport.sessionCookie);
      addTearDown(h.dispose);
      h.backend
        ..on(
          ApiEndpoints.login,
          200,
          ok(<String, Object?>{'user': userPayload, 'mode': 'cookie'}),
        )
        ..on(
          ApiEndpoints.contextTenants,
          200,
          ok(<String, Object?>{'tenants': <Object?>[]}),
        );

      final state = await h.service.signIn(
        identifier: 'rina',
        password: kPassword,
      );

      expect(state.isAuthenticated, isTrue);
      final login = h.backend.requests.first;
      expect((login.data as Map<String, Object?>)['mode'], 'cookie');
      expect(h.storedToken, isNull);
      expect(login.headers.containsKey('Authorization'), isFalse);
    });
  });

  group('restoreSession', () {
    test('no stored credential means unauthenticated with no call', () async {
      final h = Harness();
      addTearDown(h.dispose);

      final state = await h.service.restoreSession();

      expect(state, const AuthState.unauthenticated());
      // Nothing was asked of the server, because there was nothing to ask about.
      expect(
        h.backend.requests.where((r) => r.path == ApiEndpoints.me),
        isEmpty,
      );
    });

    test('a stored token is ALWAYS verified against the server', () async {
      final h = Harness();
      addTearDown(h.dispose);
      await h.seedStoredSession();
      h.backend.on(
        ApiEndpoints.me,
        200,
        ok(<String, Object?>{
          'user': userPayload,
          'memberships': <Object?>[
            <String, Object?>{
              'membership_id': kMembershipId,
              'status': 'active',
              'tenant': <String, Object?>{
                'id': kTenantId,
                'name': 'Laundry Melati (fiktif)',
              },
            },
          ],
        }),
      );

      final state = await h.service.restoreSession();

      expect(state.isAuthenticated, isTrue);
      // The presence of a token proved nothing on its own; auth/me did.
      final me = h.backend.requests.singleWhere(
        (r) => r.path == ApiEndpoints.me,
      );
      expect(me.headers['Authorization'], 'Bearer $kToken');
    });

    test('a revoked session is reported and the credential cleared', () async {
      final h = Harness();
      addTearDown(h.dispose);
      await h.seedStoredSession();
      h.backend.on(ApiEndpoints.me, 401, err('SESSION_REVOKED'));

      final state = await h.service.restoreSession();

      expect(state, isA<SessionRevoked>());
      expect(h.storedToken, isNull);
    });

    test('a revoked device is distinguished from a revoked session', () async {
      final h = Harness();
      addTearDown(h.dispose);
      await h.seedStoredSession();
      h.backend.on(ApiEndpoints.me, 401, err('DEVICE_REVOKED'));

      final state = await h.service.restoreSession();

      // Kept distinct because the recovery genuinely differs: another device of
      // this user may still be fine.
      expect(state, isA<DeviceRevoked>());
      expect(h.storedToken, isNull);
    });

    test(
      'a network failure at launch does NOT delete a good credential',
      () async {
        // REGRESSION. An earlier version decided whether to clear credentials
        // from the resulting STATE. During restoration the transient fallback
        // is `unauthenticated`, so a phone with no signal looked exactly like a
        // dead session and the stored token was deleted — the user was handed a
        // password prompt because their train went into a tunnel.
        final h = Harness();
        addTearDown(h.dispose);
        await h.seedStoredSession();
        h.backend.throwOn = DioException(
          requestOptions: RequestOptions(path: ApiEndpoints.me),
          type: DioExceptionType.connectionError,
        );

        final state = await h.service.restoreSession();

        // Honest: we could not verify, so we do not claim a session.
        expect(state, const AuthState.unauthenticated());
        // But the credential survives, so the next launch with signal works.
        expect(h.storedToken, isNotNull);
      },
    );

    test(
      'an unrecognised error code never terminates the session',
      () async {
        final h = Harness();
        addTearDown(h.dispose);
        await h.seedStoredSession();
        // A code this build does not know. Guessing it into a session-ending
        // meaning would let the server log every user out by adding a string.
        h.backend.on(ApiEndpoints.me, 418, err('KODE_BARU_YANG_TIDAK_DIKENAL'));

        final state = await h.service.restoreSession();

        expect(state, const AuthState.unauthenticated());
        expect(h.storedToken, isNotNull);
      },
    );

    test('a previously chosen tenant is resumed and re-verified', () async {
      final h = Harness();
      addTearDown(h.dispose);
      await h.seedStoredSession();
      await h.store.write(
        namespace: StorageNamespace.user(kUserId),
        key: CredentialKeys.lastActiveTenantId,
        value: kTenantId,
      );
      h.backend
        ..on(
          ApiEndpoints.me,
          200,
          ok(<String, Object?>{
            'user': userPayload,
            'memberships': <Object?>[],
          }),
        )
        ..on(
          ApiEndpoints.contextTenant,
          200,
          ok(<String, Object?>{
            'context': <String, Object?>{
              'tenant': <String, Object?>{
                'id': kTenantId,
                'name': 'Laundry Melati (fiktif)',
              },
              'membership': <String, Object?>{
                'id': kMembershipId,
                'status': 'active',
              },
            },
            'permissions': <Object?>['outlet.view'],
            'roles': <Object?>['outlet_manager'],
          }),
        );

      final state = await h.service.restoreSession();

      expect(state.isAuthenticated, isTrue);
      expect(state.session!.activeTenant!.id, kTenantId);
      // Resumed, not trusted: the server was asked to confirm the selection.
      expect(
        h.backend.requests.any((r) => r.path == ApiEndpoints.contextTenant),
        isTrue,
      );
    });

    test(
      'a stored tenant that is now refused leaves the user signed in',
      () async {
        final h = Harness();
        addTearDown(h.dispose);
        await h.seedStoredSession();
        await h.store.write(
          namespace: StorageNamespace.user(kUserId),
          key: CredentialKeys.lastActiveTenantId,
          value: kTenantId,
        );
        h.backend
          ..on(
            ApiEndpoints.me,
            200,
            ok(<String, Object?>{
              'user': userPayload,
              'memberships': <Object?>[],
            }),
          )
          ..on(ApiEndpoints.contextTenant, 403, err('TENANT_ACCESS_DENIED'));

        final state = await h.service.restoreSession();

        // Signed in, no tenant context — the honest state. A stale stored
        // tenant says nothing about whether the SESSION is still good.
        expect(state.isAuthenticated, isTrue);
        expect(state.session!.requiresTenantSelection, isTrue);
        expect(h.storedToken, isNotNull);
      },
    );
  });

  group('tenant and outlet context', () {
    Future<Harness> signedIn() async {
      final h = Harness();
      h.backend
        ..on(
          ApiEndpoints.login,
          200,
          ok(<String, Object?>{'user': userPayload, 'token': kToken}),
        )
        ..on(
          ApiEndpoints.contextTenants,
          200,
          ok(<String, Object?>{
            'tenants': <Object?>[
              <String, Object?>{
                'tenant': <String, Object?>{
                  'id': kTenantId,
                  'name': 'Laundry Melati (fiktif)',
                },
                'membership': <String, Object?>{
                  'id': kMembershipId,
                  'status': 'active',
                  'selectable': true,
                },
              },
            ],
          }),
        );
      await h.service.signIn(identifier: 'rina', password: kPassword);
      return h;
    }

    void scriptTenantSelection(Harness h) => h.backend.on(
      ApiEndpoints.contextTenant,
      200,
      ok(<String, Object?>{
        'context': <String, Object?>{
          'tenant': <String, Object?>{
            'id': kTenantId,
            'name': 'Laundry Melati (fiktif)',
          },
          'membership': <String, Object?>{
            'id': kMembershipId,
            'status': 'active',
          },
        },
        'permissions': <Object?>['outlet.view', 'outlet.switch'],
        'roles': <Object?>['outlet_manager'],
      }),
    );

    test('selecting a tenant sets the context and its permissions', () async {
      final h = await signedIn();
      addTearDown(h.dispose);
      scriptTenantSelection(h);

      final state = await h.service.selectTenant(kTenantId);

      expect(state.isAuthenticated, isTrue);
      expect(state.session!.hasTenantContext, isTrue);
      expect(
        state.session!.permissions!.allows(
          'outlet.view',
          expectedTenantId: kTenantId,
        ),
        isTrue,
      );
    });

    test('the selected tenant rides on subsequent requests', () async {
      final h = await signedIn();
      addTearDown(h.dispose);
      scriptTenantSelection(h);
      await h.service.selectTenant(kTenantId);
      h.backend.on(
        ApiEndpoints.contextOutlets,
        200,
        ok(<String, Object?>{'tenant_id': kTenantId, 'outlets': <Object?>[]}),
      );

      await h.service.authorizedOutlets();

      // Without this header a token surface authenticates and then reaches no
      // tenant-scoped endpoint at all, because the backend has no session in
      // which to remember the selection.
      final outlets = h.backend.requests.last;
      expect(outlets.headers['X-Tenant-Id'], kTenantId);
    });

    test('a denied tenant is indistinguishable from a missing one', () async {
      final h = await signedIn();
      addTearDown(h.dispose);
      h.backend.on(ApiEndpoints.contextTenant, 403, err('TENANT_ACCESS_DENIED'));

      final state = await h.service.selectTenant('tnt_fiktif_milik_orang_lain');

      // accessDenied carries NO indication of whether the tenant exists.
      expect(state, isA<AccessDenied>());
      expect(state.session, isNull);
    });

    test('outlets cannot be listed before a tenant is chosen', () async {
      final h = await signedIn();
      addTearDown(h.dispose);

      final result = await h.service.authorizedOutlets();

      expect(result.isErr, isTrue);
      // Refused locally AND never sent: an unscoped outlet listing is not a
      // request worth making.
      expect(
        h.backend.requests.any((r) => r.path == ApiEndpoints.contextOutlets),
        isFalse,
      );
    });

    test('switching tenant clears the previous outlet context', () async {
      final h = await signedIn();
      addTearDown(h.dispose);
      scriptTenantSelection(h);
      await h.service.selectTenant(kTenantId);
      h.backend
        ..on(
          ApiEndpoints.contextOutlets,
          200,
          ok(<String, Object?>{
            'tenant_id': kTenantId,
            'outlets': <Object?>[
              <String, Object?>{
                'id': kOutletId,
                'name': 'Outlet Pusat (fiktif)',
                'laundry_brand_id': kBrandId,
              },
            ],
          }),
        )
        ..on(ApiEndpoints.contextOutlet, 200, ok(<String, Object?>{}));
      await h.service.authorizedOutlets();
      await h.service.selectOutlet(kOutletId);
      expect(h.service.current.session!.activeOutlet, isNotNull);

      scriptTenantSelection(h);
      final switched = await h.service.selectTenant(kTenantId);

      // An outlet from the previous tenant must never survive the switch.
      expect(switched.session!.activeOutlet, isNull);
      expect(h.credentials.context().outletId, isNull);
    });
  });

  group('signOut', () {
    test('revokes server-side and then clears the device', () async {
      final h = Harness();
      addTearDown(h.dispose);
      await h.seedStoredSession();
      h.backend
        ..on(
          ApiEndpoints.me,
          200,
          ok(<String, Object?>{
            'user': userPayload,
            'memberships': <Object?>[],
          }),
        )
        ..on(
          ApiEndpoints.logout,
          200,
          ok(<String, Object?>{'logged_out': true}),
        );
      await h.service.restoreSession();

      final state = await h.service.signOut();

      expect(state, const AuthState.loggedOut());
      expect(h.storedToken, isNull);
      // Told the server, so the token is revoked rather than merely forgotten.
      expect(
        h.backend.requests.any((r) => r.path == ApiEndpoints.logout),
        isTrue,
      );
    });

    test('clears the device even when the server call fails', () async {
      final h = Harness();
      addTearDown(h.dispose);
      await h.seedStoredSession();
      h.backend.on(
        ApiEndpoints.me,
        200,
        ok(<String, Object?>{'user': userPayload, 'memberships': <Object?>[]}),
      );
      await h.service.restoreSession();
      h.backend.on(ApiEndpoints.logout, 500, err('INTERNAL_ERROR'));

      final state = await h.service.signOut();

      // A failed logout must never leave the credential on a shared counter
      // tablet. Pressing "keluar" means the device holds nothing of yours.
      expect(state, const AuthState.loggedOut());
      expect(h.storedToken, isNull);
    });

    test('the device identifier survives sign-out', () async {
      final h = Harness();
      addTearDown(h.dispose);
      h.backend
        ..on(
          ApiEndpoints.login,
          200,
          ok(<String, Object?>{'user': userPayload, 'token': kToken}),
        )
        ..on(
          ApiEndpoints.contextTenants,
          200,
          ok(<String, Object?>{'tenants': <Object?>[]}),
        )
        ..on(ApiEndpoints.logout, 200, ok(<String, Object?>{}));
      await h.service.signIn(identifier: 'rina', password: kPassword);
      final before = h.credentials.deviceIdentifier;

      await h.service.signOut();

      // A device that renames itself on every sign-out cannot be revoked.
      expect(before, isNotNull);
      expect(h.credentials.deviceIdentifier, before);
    });
  });
}
