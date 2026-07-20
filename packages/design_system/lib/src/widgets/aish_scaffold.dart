import 'package:flutter/material.dart';

import 'context_banner.dart';

/// The standard authenticated page frame.
///
/// Its only real responsibility is that it makes the tenant context banner
/// non-optional on an authenticated screen: [tenantName] is required, so a
/// screen author cannot build an authenticated page that omits it. Rule 28
/// treats a screen without visible tenant context as a tenant-isolation design
/// defect rather than a layout preference, and this is how that is prevented at
/// the type level.
class AishScaffold extends StatelessWidget {
  const AishScaffold({
    required this.title,
    required this.tenantName,
    required this.body,
    this.outletName,
    this.onSwitchTenant,
    this.isOffline = false,
    this.actions = const <Widget>[],
    this.navigationDrawer,
    super.key,
  });

  final String title;

  /// Required. See the class comment — this is the point of the component.
  final String tenantName;

  final String? outletName;

  final VoidCallback? onSwitchTenant;

  final bool isOffline;

  final List<Widget> actions;

  final Widget? navigationDrawer;

  final Widget body;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Semantics(header: true, child: Text(title)),
      actions: actions,
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(kToolbarHeight * 0.5),
        child: ContextBanner(
          tenantName: tenantName,
          outletName: outletName,
          onSwitchTenant: onSwitchTenant,
          isOffline: isOffline,
        ),
      ),
    ),
    drawer: navigationDrawer,
    body: SafeArea(child: body),
  );
}
