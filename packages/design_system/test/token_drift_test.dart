import 'dart:convert';
import 'dart:io';

import 'package:aish_design_system/aish_design_system.dart';
import 'package:crypto/crypto.dart';
import 'package:flutter_test/flutter_test.dart';

/// Walks up from the test's working directory to the repository root.
Directory repoRoot() {
  var dir = Directory.current;
  for (var i = 0; i < 8; i++) {
    if (File('${dir.path}/docs/MASTER_SOURCE.md').existsSync()) {
      return dir;
    }
    dir = dir.parent;
  }
  fail('Could not locate the repository root from ${Directory.current.path}');
}

void main() {
  final root = repoRoot();
  final tokenDir = Directory('${root.path}/docs/design/tokens');
  final generatedDir = Directory(
    '${root.path}/packages/design_system/lib/src/generated',
  );

  group('Generated tokens match their canonical source', () {
    test('every recorded SHA256 matches the file on disk', () {
      // This is the drift gate. A token JSON edited without regenerating is a
      // FAILING TEST rather than a discrepancy somebody notices six screens
      // later.
      expect(AishTokenSources.sha256, isNotEmpty);
      AishTokenSources.sha256.forEach((name, recorded) {
        final file = File('${tokenDir.path}/$name');
        expect(file.existsSync(), isTrue, reason: '$name is missing');
        final actual = sha256.convert(file.readAsBytesSync()).toString();
        expect(
          actual,
          recorded,
          reason:
              '$name changed but the Dart output was not regenerated. '
              'Run: python3 scripts/generate-design-tokens.py',
        );
      });
    });

    test('all ten canonical source files are accounted for', () {
      expect(AishTokenSources.sha256.length, 10);
    });

    test('the generated theme is light, and only light', () {
      expect(AishTokenSources.theme, 'light');
    });
  });

  group('Generated files are machine-owned', () {
    test('every generated file carries the DO NOT EDIT banner', () {
      final files = generatedDir.listSync().whereType<File>().where(
        (f) => f.path.endsWith('.dart'),
      );
      expect(files, isNotEmpty);
      for (final file in files) {
        expect(
          file.readAsStringSync(),
          contains('GENERATED — DO NOT EDIT'),
          reason: '${file.path} lacks the generated banner',
        );
      }
    });
  });

  group('No raw values outside the generated output', () {
    /// A hex colour literal in Dart source.
    final hexPattern = RegExp(r'0x[0-9a-fA-F]{8}|#[0-9a-fA-F]{6}\b');

    test('no hand-written hex colour exists in any hand-written source', () {
      // The token layer is only a source of truth if nothing bypasses it. This
      // test is the mechanical half of Rule 26 rule 1; the review is the other
      // half.
      final offenders = <String>[];
      for (final dir in <String>[
        'packages/design_system/lib',
        'packages/core/lib',
        'packages/domain/lib',
        'packages/auth/lib',
        'packages/networking/lib',
        'packages/local_storage/lib',
        'packages/offline_sync/lib',
        'packages/observability/lib',
        'apps/customer_android/lib',
        'apps/ops_android/lib',
        'apps/admin_web/lib',
      ]) {
        final directory = Directory('${root.path}/$dir');
        if (!directory.existsSync()) {
          continue;
        }
        for (final file
            in directory
                .listSync(recursive: true)
                .whereType<File>()
                .where((f) => f.path.endsWith('.dart'))) {
          // The generated directory is where raw values legitimately live.
          if (file.path.contains('/generated/')) {
            continue;
          }
          final lines = const LineSplitter().convert(file.readAsStringSync());
          for (var i = 0; i < lines.length; i++) {
            if (hexPattern.hasMatch(lines[i])) {
              offenders.add(
                '${file.path.replaceFirst(root.path, '')}:${i + 1}: '
                '${lines[i].trim()}',
              );
            }
          }
        }
      }
      expect(
        offenders,
        isEmpty,
        reason:
            'Raw colour values found outside the generated output. '
            'Add a semantic token instead:\n${offenders.join('\n')}',
      );
    });
  });

  group('Token values carry the non-negotiable constraints', () {
    test('the minimum touch target is 48', () {
      expect(AishSizing.sizeTouchMin, 48.0);
      expect(AishTheme.minimumTouchTarget, 48.0);
    });

    test('spacing sits on a 4px grid', () {
      for (final value in <double>[
        AishSpacing.space1,
        AishSpacing.space2,
        AishSpacing.space3,
        AishSpacing.space4,
        AishSpacing.space6,
      ]) {
        expect(value % 4, 0, reason: '$value is off the 4px grid');
      }
    });

    test('the focus ring has a non-zero width', () {
      // A focus indicator that renders at zero width does not exist.
      expect(AishBorders.borderWidthFocus, greaterThan(0));
    });

    test('semantic colours resolve to concrete values', () {
      expect(AishSemanticColors.colorSemanticPrimary.a, 1.0);
      expect(AishSemanticColors.colorSemanticDanger.a, 1.0);
    });
  });
}
