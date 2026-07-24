import 'dart:convert';

import 'package:aish_core/aish_core.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:test/test.dart';

// A fixed, obviously fictional instant, so createdAt ordering is deterministic.
final DateTime _t0 = DateTime.utc(2026, 7, 24, 9);

ProductionCommand advanceCommand({
  required String reference,
  String tenantId = 'ten-A',
  String userId = 'usr-1',
  String jobId = 'job-1',
  String? outletId = 'out-1',
  int? expectedVersion = 3,
  int minute = 0,
}) => ProductionCommand(
  clientReference: reference,
  tenantId: tenantId,
  userId: userId,
  jobId: jobId,
  outletId: outletId,
  orderId: 'ord-1',
  type: ProductionCommandType.advance,
  createdAtUtc: _t0.add(Duration(minutes: minute)),
  expectedVersion: expectedVersion,
  payload: const <String, Object?>{'stage': 'WASHING'},
);

ProductionCommandQueue queueFor(
  SecureCredentialStore store, {
  String userId = 'usr-1',
  String tenantId = 'ten-A',
}) => ProductionCommandQueue(
  store: store,
  userId: userId,
  tenantId: tenantId,
);

void main() {
  group('schema / local migration', () {
    test('a fresh queue stamps the current schema version', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);

      final result = await queue.ensureSchema();
      expect(result.isOk, isTrue);
      // The version marker is persisted through the secure store.
      expect(
        store.keys.any((k) => k.contains('prod_schema_version')),
        isTrue,
      );
    });

    test(
      'ensureSchema is idempotent and preserves an existing queued command',
      () async {
        final store = InMemoryCredentialStore();
        final queue = queueFor(store);

        await queue.enqueue(advanceCommand(reference: 'r1'));
        await queue.ensureSchema();
        await queue.ensureSchema();

        final all = (await queue.all()).valueOrNull!;
        expect(all.single.clientReference, 'r1');
      },
    );
  });

  group('DAO operations and durability', () {
    test('enqueue persists through the secure store (encrypted-at-rest boundary)', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);

      await queue.enqueue(advanceCommand(reference: 'r1'));

      // Persistence flows through the SecureCredentialStore — on device this IS
      // the keystore-backed encrypted boundary. Nothing is written elsewhere.
      final commandKey = store.keys.firstWhere((k) => k.contains('prod_cmd/r1'));
      // Namespaced per user+tenant, so a cross-context read is impossible to
      // write by accident.
      expect(commandKey, contains('user:usr-1:tenant:ten-A'));
    });

    test('a command survives a fresh queue instance (process restart)', () async {
      final store = InMemoryCredentialStore();
      await queueFor(store).enqueue(advanceCommand(reference: 'r1'));

      // A brand-new queue object over the SAME store — as after an app kill.
      final revived = queueFor(store);
      final all = (await revived.all()).valueOrNull!;
      expect(all.single.clientReference, 'r1');
      expect(all.single.payload['stage'], 'WASHING');
      // The optimistic token and outlet round-trip exactly.
      expect(all.single.expectedVersion, 3);
      expect(all.single.outletId, 'out-1');
    });

    test('all() returns commands oldest-first', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);
      await queue.enqueue(advanceCommand(reference: 'late', minute: 10));
      await queue.enqueue(advanceCommand(reference: 'early', minute: 1));

      final all = (await queue.all()).valueOrNull!;
      expect(all.map((c) => c.clientReference), <String>['early', 'late']);
    });
  });

  group('idempotency / duplicate taps', () {
    test('a second enqueue of the same reference does not duplicate', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);

      await queue.enqueue(advanceCommand(reference: 'r1'));
      final second = await queue.enqueue(advanceCommand(reference: 'r1'));

      expect(second.isOk, isTrue);
      final all = (await queue.all()).valueOrNull!;
      expect(all, hasLength(1));
    });

    test('localId equals clientReference and cannot drift', () {
      final command = advanceCommand(reference: 'ref-xyz');
      expect(command.localId, 'ref-xyz');
      final advanced = command.copyWith(
        status: ProductionCommandStatus.syncing,
      );
      // A transition never re-keys the row.
      expect(advanced.localId, 'ref-xyz');
    });
  });

  group('tenant / user / outlet separation', () {
    test('a queue for tenant A cannot see tenant B commands', () async {
      final store = InMemoryCredentialStore();
      await queueFor(store, tenantId: 'ten-A')
          .enqueue(advanceCommand(reference: 'a1', tenantId: 'ten-A'));
      await queueFor(store, tenantId: 'ten-B')
          .enqueue(advanceCommand(reference: 'b1', tenantId: 'ten-B'));

      final a = (await queueFor(store, tenantId: 'ten-A').all()).valueOrNull!;
      final b = (await queueFor(store, tenantId: 'ten-B').all()).valueOrNull!;
      expect(a.single.clientReference, 'a1');
      expect(b.single.clientReference, 'b1');
    });

    test('a queue for user 1 cannot see user 2 commands', () async {
      final store = InMemoryCredentialStore();
      await queueFor(store, userId: 'usr-1')
          .enqueue(advanceCommand(reference: 'u1', userId: 'usr-1'));
      await queueFor(store, userId: 'usr-2')
          .enqueue(advanceCommand(reference: 'u2', userId: 'usr-2'));

      final one = (await queueFor(store, userId: 'usr-1').all()).valueOrNull!;
      expect(one.single.clientReference, 'u1');
      expect(one.single.userId, 'usr-1');
    });

    test('a command carries its outlet through persistence', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);
      await queue.enqueue(
        advanceCommand(reference: 'r1', outletId: 'out-99'),
      );
      final all = (await queueFor(store).all()).valueOrNull!;
      expect(all.single.outletId, 'out-99');
    });
  });

  group('guarded terminal SYNCED state', () {
    test('updateGuarded refuses to overwrite a synced row', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);
      await queue.enqueue(advanceCommand(reference: 'r1'));

      // First: the canonical success.
      await queue.updateGuarded(
        'r1',
        (c) => c.copyWith(
          status: ProductionCommandStatus.synced,
          serverVersion: 4,
          acknowledgement: const <String, Object?>{'job': <String, Object?>{}},
        ),
      );

      // Then a LOSING writer tries to move it to retryWait — refused.
      final losing = await queue.updateGuarded(
        'r1',
        (c) => c.copyWith(status: ProductionCommandStatus.retryWait),
      );
      expect(losing.valueOrNull!.status, ProductionCommandStatus.synced);

      final stored = (await queue.byReference('r1')).valueOrNull!;
      expect(stored.status, ProductionCommandStatus.synced);
      expect(stored.serverVersion, 4);
    });

    test(
      'worker/manual race: whichever writes synced wins; the loser cannot clobber it',
      () async {
        final store = InMemoryCredentialStore();
        final queue = queueFor(store);
        await queue.enqueue(advanceCommand(reference: 'r1'));

        // The "worker" succeeds and stores the canonical result.
        await queue.updateGuarded(
          'r1',
          (c) => c.copyWith(status: ProductionCommandStatus.synced),
        );
        // The "manual retry" path, which timed out AFTER the server had
        // committed, tries to record a permanent failure. It must not win.
        await queue.updateGuarded(
          'r1',
          (c) => c.copyWith(
            status: ProductionCommandStatus.failedPermanent,
            permanentFailureDetail: 'timeout',
          ),
        );

        final stored = (await queue.byReference('r1')).valueOrNull!;
        expect(stored.status, ProductionCommandStatus.synced);
        expect(stored.permanentFailureDetail, isNull);
      },
    );

    test('a transition may not re-key a command', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);
      await queue.enqueue(advanceCommand(reference: 'r1'));

      final result = await queue.updateGuarded(
        'r1',
        (c) => ProductionCommand(
          clientReference: 'DIFFERENT',
          tenantId: c.tenantId,
          userId: c.userId,
          jobId: c.jobId,
          type: c.type,
          createdAtUtc: c.createdAtUtc,
        ),
      );
      expect(result.isErr, isTrue);
    });
  });

  group('manual resolution and logout protection', () {
    test('resolveManually removes the row and requires a reason', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);
      await queue.enqueue(advanceCommand(reference: 'r1'));

      expect(
        () => queue.resolveManually(clientReference: 'r1', reason: '  '),
        throwsA(isA<ArgumentError>()),
      );

      final ok = await queue.resolveManually(
        clientReference: 'r1',
        reason: 'Dibatalkan operator',
      );
      expect(ok.isOk, isTrue);
      expect((await queue.all()).valueOrNull!, isEmpty);
    });

    test('hasUnacknowledged reflects pending work for the logout flow', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);
      expect((await queue.hasUnacknowledged()).valueOrNull, isFalse);

      await queue.enqueue(advanceCommand(reference: 'r1'));
      expect((await queue.hasUnacknowledged()).valueOrNull, isTrue);

      await queue.updateGuarded(
        'r1',
        (c) => c.copyWith(status: ProductionCommandStatus.synced),
      );
      expect((await queue.hasUnacknowledged()).valueOrNull, isFalse);
    });

    test(
      'clearing the credential namespace leaves the tenant queue intact',
      () async {
        final store = InMemoryCredentialStore();
        final queue = queueFor(store, userId: 'usr-1', tenantId: 'ten-A');
        await queue.enqueue(advanceCommand(reference: 'r1'));

        // A credential-scoped logout clears the USER namespace (where the
        // session token lives), not the tenant queue namespace.
        await store.clearNamespace(StorageNamespace.user('usr-1'));

        final all = (await queue.all()).valueOrNull!;
        expect(all.single.clientReference, 'r1');
      },
    );
  });

  group('fail-closed on corrupt payload and storage failure', () {
    test('a corrupt command record is dropped, not fatal', () async {
      final store = InMemoryCredentialStore();
      final queue = queueFor(store);
      await queue.enqueue(advanceCommand(reference: 'good'));

      // Corrupt one record directly in the store, and add it to the index.
      final ns = StorageNamespace.tenant(userId: 'usr-1', tenantId: 'ten-A');
      await store.write(
        namespace: ns,
        key: 'prod_cmd/bad',
        value: 'this is not json {',
      );
      final rawIndex = (await store.read(
        namespace: ns,
        key: 'prod_cmd_index',
      )).valueOrNull!;
      final refs = (jsonDecode(rawIndex) as List).cast<String>()..add('bad');
      await store.write(
        namespace: ns,
        key: 'prod_cmd_index',
        value: jsonEncode(refs),
      );

      final all = (await queue.all()).valueOrNull!;
      // The good one survives; the corrupt one is dropped and pruned.
      expect(all.map((c) => c.clientReference), <String>['good']);
      final prunedIndex = (await store.read(
        namespace: ns,
        key: 'prod_cmd_index',
      )).valueOrNull!;
      expect((jsonDecode(prunedIndex) as List).contains('bad'), isFalse);
    });

    test('a secure-storage failure fails closed (Err, never a false empty)', () async {
      final store = InMemoryCredentialStore()..failEverything = true;
      final queue = queueFor(store);

      final enqueue = await queue.enqueue(advanceCommand(reference: 'r1'));
      expect(enqueue.isErr, isTrue);
      expect(enqueue.failureOrNull!.kind, FailureKind.storage);

      final all = await queue.all();
      expect(all.isErr, isTrue);
    });
  });
}
