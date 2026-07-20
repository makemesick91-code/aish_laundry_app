import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Accessibility assertions used across the surfaces' widget tests.
///
/// These exist so an accessibility requirement is checked mechanically rather
/// than reviewed by eye. A rule that is only ever read is a rule that is
/// eventually forgotten under schedule pressure.
///
/// What they do NOT do, stated plainly: none of this is an accessibility audit.
/// These are unit-level checks of specific properties on specific widgets in a
/// test harness. Runtime conformance verification is a later Step, and the
/// permitted wording remains
/// DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.
abstract final class A11y {
  /// The non-negotiable minimum interactive target, in logical pixels.
  static const double minimumTouchTarget = 48.0;

  /// Assert every tappable widget of type [T] meets the minimum target.
  ///
  /// Checks the rendered size, not a declared constraint, because a constraint
  /// that a parent overrides is not a guarantee.
  static void expectMinimumTouchTargets<T extends Widget>(
    WidgetTester tester, {
    double minimum = minimumTouchTarget,
  }) {
    final finder = find.byType(T);
    expect(
      finder,
      findsWidgets,
      reason: 'No $T found — the assertion would otherwise pass vacuously.',
    );
    for (final element in finder.evaluate()) {
      final size = element.size;
      expect(size, isNotNull, reason: '$T has no rendered size.');
      expect(
        size!.width >= minimum && size.height >= minimum,
        isTrue,
        reason:
            '$T rendered at ${size.width}x${size.height}, below the '
            '${minimum}x$minimum minimum. A target below this is an '
            'accessibility defect, not a density choice.',
      );
    }
  }

  /// Assert a widget exposes a non-empty accessible name.
  static void expectSemanticLabel(WidgetTester tester, Finder finder) {
    final node = tester.getSemantics(finder);
    final label = node.label;
    expect(
      label.trim().isNotEmpty,
      isTrue,
      reason:
          'Widget exposes no accessible name. An icon-only control with no '
          'text alternative is rejected.',
    );
  }

  /// Assert some text is present, so a state is never carried by colour alone.
  static void expectTextualState(String expected) {
    expect(
      find.textContaining(expected, findRichText: true),
      findsWidgets,
      reason:
          'State "$expected" is not rendered as text. Colour alone is not '
          'an acceptable carrier of state.',
    );
  }

  /// Assert focus currently rests on the widget found by [finder].
  static void expectFocused(WidgetTester tester, Finder finder) {
    final focusNode = tester.widget<Focus>(
      find.descendant(of: finder, matching: find.byType(Focus)).first,
    );
    expect(
      focusNode.focusNode?.hasFocus ?? false,
      isTrue,
      reason:
          'Expected focus to rest on this widget. Focus lost on route '
          'change, dialogue open or validation error is a defect.',
    );
  }

  /// Assert the tree survives a large text scale without overflowing.
  ///
  /// A render overflow becomes a test failure via [tester.takeException], so
  /// truncation or clipping of critical information at a large font size is
  /// caught here rather than on a user's phone.
  static Future<void> expectSurvivesTextScale(
    WidgetTester tester,
    Widget Function() build, {
    double scale = 2.0,
  }) async {
    await tester.pumpWidget(
      MediaQuery(
        data: MediaQueryData(textScaler: TextScaler.linear(scale)),
        child: build(),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      tester.takeException(),
      isNull,
      reason:
          'Layout failed at text scale $scale. Reflow is expected; '
          'truncating critical content is a defect.',
    );
  }
}
