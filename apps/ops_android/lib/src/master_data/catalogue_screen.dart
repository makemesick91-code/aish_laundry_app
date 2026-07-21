import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/ops_routes.dart';
import 'master_data_providers.dart';
import 'master_data_views.dart';
import 'ops_master_data_scaffold.dart';

/// THE COUNTER'S VIEW OF WHAT IS SOLD AND WHAT IT COSTS (FR-031 … FR-040).
///
/// READ-ONLY, AND THAT IS THE DESIGN.
/// The catalogue and the price list are managed in the Console, by roles that
/// hold `service.manage` and `price_list.manage`. A counter operator needs to
/// LOOK UP a price while a customer is standing there; giving them an edit
/// control they will never hold the permission for would be a dead end
/// (Rule 29), and giving them one they DID hold would put price authorship at
/// the counter, which is not where FR-034 puts it.
///
/// A PRICE SHOWN HERE IS A PRICE-LIST ENTRY, NOT A QUOTED TOTAL.
/// Nothing on this screen multiplies a price by a quantity, sums anything, or
/// produces a figure a customer would be charged. An order and its total are
/// Step 5 (Rule 42, DEC-0030), and a client-computed total is display-only even
/// then — totals are computed and authoritative on the server (Rule 04).
///
/// EVERY AMOUNT IS AN INTEGER RUPIAH.
/// [Rupiah] holds an `int`, refuses a `double` and a formatted string, and
/// offers no arithmetic at all. There is no floating-point value anywhere on
/// this path (Rule 04 hard rule 2).
class CatalogueScreen extends ConsumerStatefulWidget {
  const CatalogueScreen({super.key});

  @override
  ConsumerState<CatalogueScreen> createState() => _CatalogueScreenState();
}

class _CatalogueScreenState extends ConsumerState<CatalogueScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 3, vsync: this);

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    final repository = ref.watch(masterDataRepositoryProvider);

    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    return OpsMasterDataScaffold(
      title: 'Layanan dan harga',
      session: session,
      onBack: () => context.go(OpsRoutes.home),
      body: Column(
        children: <Widget>[
          TabBar(
            controller: _tabs,
            tabs: const <Widget>[
              Tab(text: 'Layanan'),
              Tab(text: 'Paket'),
              Tab(text: 'Daftar harga'),
            ],
          ),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: <Widget>[
                OpsAsyncSection<CatalogService>(
                  load: repository.services,
                  emptyTitle: 'Katalog layanan masih kosong',
                  emptyDescription:
                      'Layanan yang ditambahkan admin tenant akan muncul di sini.',
                  builder: (context, items) => ListView.builder(
                    itemCount: items.length,
                    itemBuilder: (context, index) {
                      final service = items[index];

                      return ListTile(
                        title: Text(service.name),
                        subtitle: Text(
                          // The unit is spelled out with its measure, so a
                          // reader never has to guess whether a minimum of 2000
                          // is grams or items.
                          service.minimumQuantity == null
                              ? '${service.code} · ${service.unitKind.label}'
                              : '${service.code} · ${service.unitKind.label} · '
                                    'min ${service.minimumQuantity} '
                                    '${service.unitKind.quantityUnitLabel}',
                        ),
                        trailing: service.isActive
                            ? null
                            : const StatusChip(
                                label: 'Nonaktif',
                                icon: Icons.pause_circle_outline,
                                tone: StatusTone.neutral,
                              ),
                      );
                    },
                  ),
                ),
                OpsAsyncSection<CatalogPackage>(
                  load: repository.packages,
                  emptyTitle: 'Belum ada paket layanan',
                  emptyDescription:
                      'Paket menggabungkan beberapa layanan menjadi satu entri '
                      'katalog.',
                  builder: (context, items) => ListView.builder(
                    itemCount: items.length,
                    itemBuilder: (context, index) {
                      final package = items[index];

                      return ListTile(
                        title: Text(package.name),
                        subtitle: Text(
                          '${package.code} · ${package.items.length} layanan',
                        ),
                        trailing: package.isActive
                            ? null
                            : const StatusChip(
                                label: 'Nonaktif',
                                icon: Icons.pause_circle_outline,
                                tone: StatusTone.neutral,
                              ),
                      );
                    },
                  ),
                ),
                OpsAsyncSection<PriceListSummary>(
                  // ACTIVE lists only, on this surface specifically. A counter
                  // operator answering "how much is this" must not read a price
                  // off a DRAFT that has never been published and may never be.
                  load: () => repository.priceLists(status: 'active'),
                  emptyTitle: 'Belum ada daftar harga aktif',
                  emptyDescription:
                      'Daftar harga diterbitkan per brand oleh admin tenant. '
                      'Harga hanya berlaku setelah diterbitkan.',
                  builder: (context, items) => ListView.builder(
                    itemCount: items.length,
                    itemBuilder: (context, index) =>
                        _PriceListCard(list: items[index]),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PriceListCard extends StatelessWidget {
  const _PriceListCard({required this.list});

  final PriceListSummary list;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Card(
      margin: EdgeInsets.symmetric(
        horizontal: AishSpacing.space4,
        vertical: AishSpacing.space2,
      ),
      child: Padding(
        padding: EdgeInsets.all(AishSpacing.space4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(child: Text(list.name, style: textTheme.titleSmall)),
                StatusChip(
                  label: list.status.label,
                  icon: switch (list.status) {
                    PriceListStatus.draft => Icons.edit_note_outlined,
                    PriceListStatus.active => Icons.check_circle_outline,
                    PriceListStatus.superseded => Icons.history_outlined,
                    PriceListStatus.archived => Icons.archive_outlined,
                  },
                  tone: switch (list.status) {
                    PriceListStatus.draft => StatusTone.information,
                    PriceListStatus.active => StatusTone.success,
                    PriceListStatus.superseded ||
                    PriceListStatus.archived => StatusTone.neutral,
                  },
                ),
              ],
            ),
            SizedBox(height: AishSpacing.space1),
            Text(
              list.effectiveUntil == null
                  ? '${list.code} · berlaku sejak ${list.effectiveFrom}'
                  : '${list.code} · ${list.effectiveFrom} s.d. ${list.effectiveUntil}',
              style: textTheme.bodySmall,
            ),
            if (list.items.isNotEmpty) ...<Widget>[
              SizedBox(height: AishSpacing.space3),
              ...list.items.map(
                (entry) => Padding(
                  padding: EdgeInsets.only(bottom: AishSpacing.space1),
                  child: Row(
                    children: <Widget>[
                      Expanded(child: Text(entry.targetId)),
                      // Formatted for display from an INTEGER. `Rupiah.formatted`
                      // is one-way: nothing parses a formatted string back into
                      // an amount, so a display convention can never become a
                      // value (Rule 04).
                      Text(entry.amount.formatted, style: textTheme.bodyMedium),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
