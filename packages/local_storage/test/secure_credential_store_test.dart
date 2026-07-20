import 'package:aish_local_storage/aish_local_storage.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('StorageNamespace', () {
    test('user and tenant namespaces are distinct key spaces', () {
      final userSpace = StorageNamespace.user('usr_1');
      final tenantSpace = StorageNamespace.tenant(
        userId: 'usr_1',
        tenantId: 'tnt_a',
      );
      expect(userSpace.qualify('k'), isNot(tenantSpace.qualify('k')));
    });

    test('two tenants of the SAME user never collide', () {
      final a = StorageNamespace.tenant(userId: 'usr_1', tenantId: 'tnt_a');
      final b = StorageNamespace.tenant(userId: 'usr_1', tenantId: 'tnt_b');
      expect(a.qualify('session_token'), isNot(b.qualify('session_token')));
      expect(a.owns(b.qualify('session_token')), isFalse);
    });

    test('two users on one device never collide', () {
      final a = StorageNamespace.user('usr_1');
      final b = StorageNamespace.user('usr_2');
      expect(a.owns(b.qualify('k')), isFalse);
    });

    test('rejects an identifier containing a separator', () {
      // Without this, a userId of "a:tenant:b" could impersonate another
      // namespace.
      expect(
        () => StorageNamespace.tenant(userId: 'a:tenant:b', tenantId: 'x'),
        throwsArgumentError,
      );
    });

    test('rejects empty identifiers and keys', () {
      expect(() => StorageNamespace.user('  '), throwsArgumentError);
      expect(
        () => StorageNamespace.user('usr_1').qualify(''),
        throwsArgumentError,
      );
    });
  });

  group('InMemoryCredentialStore', () {
    late InMemoryCredentialStore store;
    final userSpace = StorageNamespace.user('usr_1');
    final melati = StorageNamespace.tenant(
      userId: 'usr_1',
      tenantId: 'tnt_melati',
    );
    final kenanga = StorageNamespace.tenant(
      userId: 'usr_1',
      tenantId: 'tnt_kenanga',
    );

    setUp(() => store = InMemoryCredentialStore());

    test('round-trips a value within a namespace', () async {
      await store.write(
        namespace: userSpace,
        key: CredentialKeys.sessionToken,
        value: 'nilai_fiktif',
      );
      final read = await store.read(
        namespace: userSpace,
        key: CredentialKeys.sessionToken,
      );
      expect(read.valueOrNull, 'nilai_fiktif');
    });

    test('a read in one tenant never sees the other tenant value', () async {
      await store.write(
        namespace: melati,
        key: CredentialKeys.lastActiveOutletId,
        value: 'otl_melati',
      );
      final crossRead = await store.read(
        namespace: kenanga,
        key: CredentialKeys.lastActiveOutletId,
      );
      expect(crossRead.valueOrNull, isNull);
    });

    test('clearNamespace removes only that namespace', () async {
      await store.write(namespace: melati, key: 'k', value: 'v_melati');
      await store.write(namespace: kenanga, key: 'k', value: 'v_kenanga');
      await store.clearNamespace(melati);

      expect(
        (await store.read(namespace: melati, key: 'k')).valueOrNull,
        isNull,
      );
      expect(
        (await store.read(namespace: kenanga, key: 'k')).valueOrNull,
        'v_kenanga',
      );
    });

    test(
      'clearOnLogout removes EVERY namespace, not just the active one',
      () async {
        // A user signing out on a shared counter device expects the device to
        // hold nothing of theirs — including the tenant they switched away from.
        await store.write(
          namespace: userSpace,
          key: CredentialKeys.sessionToken,
          value: 't',
        );
        await store.write(namespace: melati, key: 'k', value: 'v');
        await store.write(namespace: kenanga, key: 'k', value: 'v');

        await store.clearOnLogout();

        expect(store.keys, isEmpty);
        expect(
          (await store.read(
            namespace: userSpace,
            key: CredentialKeys.sessionToken,
          )).valueOrNull,
          isNull,
        );
        expect(
          (await store.read(namespace: melati, key: 'k')).valueOrNull,
          isNull,
        );
        expect(
          (await store.read(namespace: kenanga, key: 'k')).valueOrNull,
          isNull,
        );
      },
    );

    test('a storage failure returns a Failure rather than throwing', () async {
      store.failEverything = true;
      final result = await store.read(namespace: userSpace, key: 'k');
      expect(result.isErr, isTrue);
      expect(result.failureOrNull!.kind.name, 'storage');
    });

    test('a failure message never names the key', () async {
      store.failEverything = true;
      final result = await store.read(
        namespace: userSpace,
        key: CredentialKeys.sessionToken,
      );
      // A key name discloses which credentials exist.
      expect(
        result.failureOrNull!.message,
        isNot(contains(CredentialKeys.sessionToken)),
      );
    });
  });
}
