import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';

/// Renders every design-system component once, with no data.
///
/// Its purpose is to prove the generated token layer resolves and the components
/// build — nothing more. It is NOT a storybook, NOT a component library, and NOT
/// evidence that any screen works. It reaches no network and shows no record.
class DesignSmokeScreen extends StatelessWidget {
  const DesignSmokeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(
        title: Semantics(
          header: true,
          child: const Text('Pratinjau design system'),
        ),
      ),
      body: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          Text('Tema terang (satu-satunya tema)', style: textTheme.titleMedium),
          SizedBox(height: AishSpacing.space2),
          Text(
            'Mode gelap DITANGGUHKAN dan tidak tersedia.',
            style: textTheme.bodySmall?.copyWith(
              color: AishSemanticColors.colorSemanticTextSecondary,
            ),
          ),
          SizedBox(height: AishSpacing.space6),
          Text('Status', style: textTheme.titleSmall),
          SizedBox(height: AishSpacing.space2),
          Wrap(
            spacing: AishSpacing.space2,
            runSpacing: AishSpacing.space2,
            children: const <Widget>[
              StatusChip(
                label: 'Tersinkron',
                icon: Icons.cloud_done_outlined,
                tone: StatusTone.success,
              ),
              StatusChip(
                label: 'Menunggu sinkronisasi',
                icon: Icons.sync_outlined,
                tone: StatusTone.syncing,
              ),
              StatusChip(
                label: 'Luring',
                icon: Icons.cloud_off_outlined,
                tone: StatusTone.offline,
              ),
              StatusChip(
                label: 'Perlu tindakan',
                icon: Icons.priority_high_outlined,
                tone: StatusTone.danger,
              ),
            ],
          ),
          SizedBox(height: AishSpacing.space6),
          Text('Tindakan', style: textTheme.titleSmall),
          SizedBox(height: AishSpacing.space2),
          PrimaryAction(
            label: 'Tindakan utama',
            semanticLabel: 'Contoh tindakan utama pratinjau',
            onPressed: () {},
          ),
          SizedBox(height: AishSpacing.space2),
          const PrimaryAction(
            label: 'Sedang memproses',
            isBusy: true,
            onPressed: null,
          ),
          SizedBox(height: AishSpacing.space6),
          Text('Konteks', style: textTheme.titleSmall),
          SizedBox(height: AishSpacing.space2),
          const ContextBanner(
            tenantName: 'Contoh Tenant (fiktif)',
            outletName: 'Contoh Outlet (fiktif)',
          ),
        ],
      ),
    );
  }
}
