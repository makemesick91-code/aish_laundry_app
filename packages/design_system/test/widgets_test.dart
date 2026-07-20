import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget host(Widget child) => MaterialApp(
  theme: AishTheme.light(),
  home: Scaffold(body: child),
);

void main() {
  group('StatusChip never carries state by colour alone', () {
    testWidgets('renders the text label and an icon', (tester) async {
      await tester.pumpWidget(
        host(
          const StatusChip(
            label: 'Menunggu sinkronisasi',
            icon: Icons.sync_outlined,
            tone: StatusTone.syncing,
          ),
        ),
      );
      expect(find.text('Menunggu sinkronisasi'), findsOneWidget);
      expect(find.byIcon(Icons.sync_outlined), findsOneWidget);
    });

    testWidgets('announces the status as text', (tester) async {
      await tester.pumpWidget(
        host(const StatusChip(label: 'Luring', icon: Icons.cloud_off_outlined)),
      );
      final semantics = tester.getSemantics(find.byType(StatusChip));
      expect(semantics.label, contains('Luring'));
    });

    testWidgets('an empty label is rejected at construction', (tester) async {
      // There is no way to build a chip that shows only a colour.
      expect(
        () => StatusChip(label: '', icon: Icons.circle),
        throwsAssertionError,
      );
    });

    testWidgets('survives a large text scale without overflowing', (
      tester,
    ) async {
      await tester.pumpWidget(
        MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(2.5)),
          child: host(
            const SizedBox(
              width: 200,
              child: StatusChip(
                label: 'Perlu tindakan segera dari operator',
                icon: Icons.priority_high_outlined,
                tone: StatusTone.danger,
              ),
            ),
          ),
        ),
      );
      expect(tester.takeException(), isNull);
    });
  });

  group('PrimaryAction', () {
    testWidgets('meets the 48x48 minimum target', (tester) async {
      await tester.pumpWidget(
        host(PrimaryAction(label: 'Simpan', onPressed: () {})),
      );
      final size = tester.getSize(find.byType(PrimaryAction));
      expect(size.height, greaterThanOrEqualTo(48.0));
      expect(size.width, greaterThanOrEqualTo(48.0));
    });

    testWidgets('exposes an accessible name naming the action', (tester) async {
      await tester.pumpWidget(
        host(
          PrimaryAction(
            label: 'Batal',
            semanticLabel: 'Batalkan pemilihan outlet',
            onPressed: () {},
          ),
        ),
      );
      final semantics = tester.getSemantics(find.byType(PrimaryAction));
      expect(semantics.label, 'Batalkan pemilihan outlet');
    });

    testWidgets('is disabled while busy so a double tap cannot double submit', (
      tester,
    ) async {
      var taps = 0;
      await tester.pumpWidget(
        host(
          PrimaryAction(label: 'Kirim', isBusy: true, onPressed: () => taps++),
        ),
      );
      await tester.tap(find.byType(PrimaryAction), warnIfMissed: false);
      await tester.pump();
      expect(taps, 0);
    });

    testWidgets('a null callback renders a disabled control', (tester) async {
      await tester.pumpWidget(
        host(const PrimaryAction(label: 'Tidak aktif', onPressed: null)),
      );
      final semantics = tester.getSemantics(find.byType(PrimaryAction));
      // `isEnabled` is a tristate, so assert it is explicitly NOT enabled
      // rather than merely "not true" — an unset flag would satisfy the
      // weaker form while telling assistive technology nothing.
      expect(semantics.flagsCollection.isEnabled.name, 'isFalse');
    });
  });

  group('ContextBanner makes tenant context non-optional', () {
    testWidgets('renders the tenant name as TEXT', (tester) async {
      await tester.pumpWidget(
        host(const ContextBanner(tenantName: 'Laundry Melati (fiktif)')),
      );
      expect(find.textContaining('Laundry Melati (fiktif)'), findsOneWidget);
    });

    testWidgets('renders the outlet alongside the tenant', (tester) async {
      await tester.pumpWidget(
        host(
          const ContextBanner(
            tenantName: 'Laundry Melati (fiktif)',
            outletName: 'Outlet Pusat (fiktif)',
          ),
        ),
      );
      expect(find.textContaining('Outlet Pusat (fiktif)'), findsOneWidget);
    });

    testWidgets('announces the working context as one phrase', (tester) async {
      await tester.pumpWidget(
        host(
          const ContextBanner(
            tenantName: 'Laundry Melati (fiktif)',
            outletName: 'Outlet Pusat (fiktif)',
          ),
        ),
      );
      final semantics = tester.getSemantics(find.byType(ContextBanner));
      expect(semantics.label, contains('Laundry Melati (fiktif)'));
      expect(semantics.label, contains('Outlet Pusat (fiktif)'));
    });

    testWidgets('an empty tenant name is rejected at construction', (
      tester,
    ) async {
      expect(() => ContextBanner(tenantName: ''), throwsAssertionError);
    });

    testWidgets('the offline marker carries text as well as an icon', (
      tester,
    ) async {
      await tester.pumpWidget(
        host(
          const ContextBanner(
            tenantName: 'Laundry Melati (fiktif)',
            isOffline: true,
          ),
        ),
      );
      expect(find.text('Luring'), findsOneWidget);
      expect(find.byIcon(Icons.cloud_off_outlined), findsOneWidget);
    });
  });

  group('AishScaffold', () {
    testWidgets('always renders the tenant banner', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AishTheme.light(),
          home: const AishScaffold(
            title: 'Beranda',
            tenantName: 'Laundry Kenanga (fiktif)',
            body: SizedBox.shrink(),
          ),
        ),
      );
      expect(find.byType(ContextBanner), findsOneWidget);
      expect(find.textContaining('Laundry Kenanga (fiktif)'), findsOneWidget);
    });
  });

  group('StateMessage', () {
    testWidgets('a half-specified recovery is rejected', (tester) async {
      // A labelled button that does nothing is worse than no button.
      expect(
        () => StateMessage(
          title: 'x',
          description: 'y',
          icon: Icons.error,
          recoveryLabel: 'Coba lagi',
        ),
        throwsAssertionError,
      );
    });

    testWidgets('renders a recovery action when one is supplied', (
      tester,
    ) async {
      var recovered = false;
      await tester.pumpWidget(
        host(
          StateMessage(
            title: 'Gagal memuat',
            description: 'Periksa koneksi Anda, lalu coba lagi.',
            icon: Icons.error_outline,
            recoveryLabel: 'Coba lagi',
            onRecover: () => recovered = true,
          ),
        ),
      );
      await tester.tap(find.text('Coba lagi'));
      await tester.pump();
      expect(recovered, isTrue);
    });

    testWidgets('describes recovery in words, never only a code', (
      tester,
    ) async {
      await tester.pumpWidget(
        host(
          const StateMessage(
            title: 'Layanan tidak tersedia',
            description: 'Coba lagi beberapa saat lagi.',
            icon: Icons.cloud_off_outlined,
          ),
        ),
      );
      expect(find.text('Coba lagi beberapa saat lagi.'), findsOneWidget);
    });
  });

  group('FutureStepPlaceholder', () {
    testWidgets('renders the literal notice verbatim', (tester) async {
      await tester.pumpWidget(
        host(
          const FutureStepPlaceholder(
            featureName: 'Kasir',
            owningStep: 'Step 5',
          ),
        ),
      );
      expect(
        find.text('NOT IMPLEMENTED — OWNED BY FUTURE CANONICAL STEP'),
        findsOneWidget,
      );
    });

    test('the notice constant is exactly the mandated string', () {
      expect(
        kFutureStepNotice,
        'NOT IMPLEMENTED — OWNED BY FUTURE CANONICAL STEP',
      );
    });

    testWidgets('names the owning Step', (tester) async {
      await tester.pumpWidget(
        host(
          const FutureStepPlaceholder(
            featureName: 'Produksi',
            owningStep: 'Step 6',
          ),
        ),
      );
      expect(find.textContaining('Step 6'), findsOneWidget);
    });
  });

  group('AishTheme', () {
    test('exposes no dark theme', () {
      // Dark mode is DEFERRED. There is deliberately no `AishTheme.dark()`;
      // if one existed a surface could opt into an unspecified theme.
      final theme = AishTheme.light();
      expect(theme.brightness, Brightness.light);
    });

    test('pads tap targets at the theme level', () {
      expect(
        AishTheme.light().materialTapTargetSize,
        MaterialTapTargetSize.padded,
      );
    });
  });
}
