import 'package:flutter/material.dart';
import '../../app/theme.dart';

class AsyncStateView extends StatelessWidget {
  final bool loading;
  final Object? error;
  final bool empty;
  final String emptyTitle;
  final String emptyMessage;
  final VoidCallback? onRetry;
  final Widget child;

  const AsyncStateView({
    super.key,
    required this.loading,
    required this.error,
    required this.empty,
    required this.child,
    this.emptyTitle = 'Aucune donnée',
    this.emptyMessage = 'Aucune information réelle n’est disponible pour le moment.',
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 46, color: OvanieColors.muted),
            const SizedBox(height: 12),
            Text('$error', textAlign: TextAlign.center),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Réessayer')),
            ],
          ]),
        ),
      );
    }
    if (empty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.inbox_outlined, size: 52, color: OvanieColors.muted),
            const SizedBox(height: 12),
            Text(emptyTitle, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text(emptyMessage, textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.muted)),
          ]),
        ),
      );
    }
    return child;
  }
}

class StatusPill extends StatelessWidget {
  final String label;
  final String? status;
  const StatusPill({super.key, required this.label, this.status});

  @override
  Widget build(BuildContext context) {
    final raw = (status ?? label).toLowerCase();
    final Color color = raw.contains('paid') || raw.contains('delivered') || raw.contains('ready') || raw.contains('active')
        ? OvanieColors.success
        : raw.contains('failed') || raw.contains('rejected') || raw.contains('cancel') || raw.contains('blocked')
            ? OvanieColors.danger
            : raw.contains('prepar') || raw.contains('processing') || raw.contains('approved')
                ? OvanieColors.blue
                : OvanieColors.warning;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(999)),
      child: Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 12)),
    );
  }
}

class SectionTitle extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget? trailing;
  const SectionTitle(this.title, {super.key, this.subtitle, this.trailing});
  @override
  Widget build(BuildContext context) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(subtitle!, style: const TextStyle(color: OvanieColors.muted)),
        ],
      ])),
      if (trailing != null) trailing!,
    ]);
  }
}
