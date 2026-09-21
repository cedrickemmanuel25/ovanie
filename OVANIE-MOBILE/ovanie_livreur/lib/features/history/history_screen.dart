import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../missions/data/mission_repository.dart';
import '../missions/models/driver_mission.dart';
import '../missions/widgets/mission_dialogs.dart';
import '../missions/widgets/mission_ui.dart';
import 'mission_history_detail_screen.dart';

/// Onglet "Historique" : missions terminées (livrées, incidents, refusées).
class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  DriverMissionListResult? _data;
  bool _loading = true;
  String? _error;
  String _query = '';
  String _filter = 'all';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!_loading && mounted) setState(() => _loading = true);
    try {
      final data = await MissionRepository.instance.fetchMissions();
      if (!mounted) return;
      setState(() {
        _data = data;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<DriverMissionSummaryModel> get _history {
    final missions = _data?.missions ?? const <DriverMissionSummaryModel>[];
    return missions.where((mission) => mission.isHistory).toList(growable: false);
  }

  List<DriverMissionSummaryModel> get _visible {
    final needle = _query.trim().toLowerCase();
    return _history.where((mission) {
      final matchesFilter = switch (_filter) {
        'delivered' => mission.isDelivered,
        'incident' => mission.hasIncident,
        'rejected' => mission.isRejected,
        _ => true,
      };
      if (!matchesFilter) return false;
      if (needle.isEmpty) return true;
      final haystack = [
        mission.missionNumber,
        mission.orderNumber ?? '',
        mission.destination ?? '',
        mission.destinationLabel ?? '',
        mission.commune ?? '',
      ].join(' ').toLowerCase();
      return haystack.contains(needle);
    }).toList(growable: false);
  }

  DateTime? _terminalDate(DriverMissionSummaryModel mission) {
    return mission.deliveredAt ?? mission.rejectedAt ?? mission.pickupScheduledAt;
  }

  Future<void> _openMission(DriverMissionSummaryModel mission) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => MissionHistoryDetailScreen(missionNumber: mission.missionNumber),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final history = _history;
    final visible = _visible;
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final weekStart = today.subtract(Duration(days: today.weekday - 1));

    final todayCount = history.where((mission) {
      final date = _terminalDate(mission);
      if (date == null) return false;
      return !date.isBefore(today);
    }).length;
    final weekCount = history.where((mission) {
      final date = _terminalDate(mission);
      if (date == null) return false;
      return !date.isBefore(weekStart);
    }).length;

    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Historique',
            subtitle: 'Vos missions précédentes',
            mainPage: true,
            trailing: MissionNotificationButton(
              count: _data?.unreadNotifications ?? 0,
              onTap: () => showMissionSnack(
                context,
                (_data?.unreadNotifications ?? 0) == 0
                    ? 'Aucune nouvelle notification.'
                    : '${_data?.unreadNotifications ?? 0} notification(s) non lue(s).',
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              color: OvanieColors.green,
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 22),
                children: [
                  _HistoryCounters(
                    today: todayCount,
                    week: weekCount,
                    total: history.length,
                  ),
                  const SizedBox(height: 15),
                  _HistorySearch(onChanged: (value) => setState(() => _query = value)),
                  const SizedBox(height: 12),
                  _HistoryFilters(
                    selected: _filter,
                    onSelected: (value) => setState(() => _filter = value),
                  ),
                  const SizedBox(height: 12),
                  if (_loading && _data == null)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 80),
                      child: Center(
                        child: CircularProgressIndicator(color: OvanieColors.green),
                      ),
                    )
                  else if (_error != null && _data == null)
                    _HistoryStateBox(
                      icon: Icons.cloud_off_rounded,
                      title: 'Impossible de charger l\'historique',
                      message: _error!,
                      buttonLabel: 'Réessayer',
                      onPressed: _load,
                    )
                  else if (visible.isEmpty)
                    const _HistoryStateBox(
                      icon: Icons.history_rounded,
                      title: 'Aucune mission dans cette catégorie',
                      message: 'Vos missions terminées apparaîtront ici.',
                    )
                  else
                    ...[
                      for (var i = 0; i < visible.length; i++) ...[
                        _HistoryCard(
                          mission: visible[i],
                          onOpen: () => _openMission(visible[i]),
                        ),
                        if (i != visible.length - 1) const SizedBox(height: 12),
                      ],
                    ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HistoryCounters extends StatelessWidget {
  const _HistoryCounters({required this.today, required this.week, required this.total});

  final int today;
  final int week;
  final int total;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: .86),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: MissionPalette.cardBorder),
      ),
      child: Row(
        children: [
          Expanded(
            child: _CounterItem(
              icon: Icons.calendar_today_rounded,
              label: 'Aujourd\'hui',
              suffix: 'livraisons',
              value: today,
            ),
          ),
          const _CounterDivider(),
          Expanded(
            child: _CounterItem(
              icon: Icons.bar_chart_rounded,
              label: 'Cette semaine',
              suffix: 'livraisons',
              value: week,
            ),
          ),
          const _CounterDivider(),
          Expanded(
            child: _CounterItem(
              icon: Icons.inventory_2_rounded,
              label: 'Total',
              suffix: 'missions',
              value: total,
            ),
          ),
        ],
      ),
    );
  }
}

class _CounterDivider extends StatelessWidget {
  const _CounterDivider();

  @override
  Widget build(BuildContext context) => Container(
        width: 1,
        height: 54,
        color: const Color(0xFFD5E3DE),
      );
}

class _CounterItem extends StatelessWidget {
  const _CounterItem({
    required this.icon,
    required this.label,
    required this.suffix,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String suffix;
  final int value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: const Color(0xFFE3F5EC),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: OvanieColors.green, size: 18),
        ),
        const SizedBox(height: 6),
        Text(
          '$value',
          style: const TextStyle(
            color: MissionPalette.navy,
            fontSize: 20,
            height: 1,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: MissionPalette.slate,
            fontSize: 11,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          suffix,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: MissionPalette.slate,
            fontSize: 10,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _HistorySearch extends StatelessWidget {
  const _HistorySearch({required this.onChanged});

  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return TextField(
      onChanged: onChanged,
      decoration: InputDecoration(
        hintText: 'Rechercher une mission, une commande ou une destination...',
        hintStyle: const TextStyle(
          color: MissionPalette.slate,
          fontSize: 12.4,
          fontWeight: FontWeight.w500,
        ),
        prefixIcon: const Icon(Icons.search_rounded, color: MissionPalette.slate, size: 27),
        filled: true,
        fillColor: Colors.white,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: MissionPalette.cardBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: MissionPalette.cardBorder),
        ),
      ),
    );
  }
}

class _HistoryFilters extends StatelessWidget {
  const _HistoryFilters({required this.selected, required this.onSelected});

  final String selected;
  final ValueChanged<String> onSelected;

  static const filters = <(String, String)>[
    ('all', 'Toutes'),
    ('delivered', 'Livrées'),
    ('incident', 'Incidents'),
    ('rejected', 'Refusées'),
  ];

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (var i = 0; i < filters.length; i++) ...[
            _FilterChip(
              label: filters[i].$2,
              selected: selected == filters[i].$1,
              onTap: () => onSelected(filters[i].$1),
            ),
            if (i != filters.length - 1) const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? OvanieColors.green : const Color(0xFFE7F4EF),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? Colors.white : MissionPalette.slate,
            fontSize: 12.3,
            fontWeight: selected ? FontWeight.w900 : FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.mission, required this.onOpen});

  final DriverMissionSummaryModel mission;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: MissionPalette.cardBorder),
        boxShadow: const [
          BoxShadow(color: Color(0x0A0B3A25), blurRadius: 14, offset: Offset(0, 4)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Mission ${mission.missionNumber}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: MissionPalette.navy,
                        fontSize: 17.2,
                        height: 1.1,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -.25,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Commande ${mission.orderNumber ?? '—'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: MissionPalette.slate,
                        fontSize: 13.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 9),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 140),
                child: MissionStatusPill.forMission(mission),
              ),
            ],
          ),
          const SizedBox(height: 13),
          MissionInfoRow(
            icon: Icons.schedule_rounded,
            label: '',
            labelWidth: 0,
            value:
                '${missionDate(mission.pickupScheduledAt)} • ${missionTime(mission.pickupScheduledAt)}',
          ),
          MissionInfoRow(
            icon: Icons.location_on_rounded,
            label: 'Destination',
            labelWidth: 78,
            value: mission.displayDestination,
          ),
          MissionInfoRow(
            icon: Icons.inventory_2_outlined,
            label: '',
            labelWidth: 0,
            value: '${mission.pickupCount} point${mission.pickupCount > 1 ? 's' : ''} de collecte',
          ),
          const SizedBox(height: 3),
          Row(
            children: [
              Expanded(
                child: MissionInfoRow(
                  icon: Icons.local_shipping_rounded,
                  label: 'Véhicule :',
                  labelWidth: 62,
                  value: mission.vehicleLabel ?? '—',
                ),
              ),
              Expanded(
                child: MissionInfoRow(
                  icon: Icons.scale_outlined,
                  label: 'Poids :',
                  labelWidth: 44,
                  value: missionWeight(mission.totalWeightKg),
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          _HistoryStatusBanner(mission: mission),
          const SizedBox(height: 11),
          MissionOutlineButton(label: 'Voir le détail', onPressed: onOpen),
        ],
      ),
    );
  }
}

class _HistoryStatusBanner extends StatelessWidget {
  const _HistoryStatusBanner({required this.mission});

  final DriverMissionSummaryModel mission;

  @override
  Widget build(BuildContext context) {
    if (mission.isDelivered) {
      return MissionTintMessage(
        text: 'Terminée à ${missionTime(mission.deliveredAt)}',
        icon: Icons.check_rounded,
      );
    }
    if (mission.hasIncident) {
      return MissionTintMessage(
        text: 'Incident signalé\n${mission.statusLabel.trim().isEmpty ? 'Un incident a été signalé sur cette mission.' : mission.statusLabel}',
        icon: Icons.warning_amber_rounded,
        warning: true,
      );
    }
    if (mission.isRejected) {
      return _DangerTintMessage(
        text: 'Mission refusée\n'
            'Motif : ${(mission.rejectionReason ?? '').trim().isEmpty ? 'Non renseigné' : mission.rejectionReason}',
        icon: Icons.cancel_rounded,
      );
    }
    return const SizedBox.shrink();
  }
}

class _DangerTintMessage extends StatelessWidget {
  const _DangerTintMessage({required this.text, required this.icon});

  final String text;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
      decoration: BoxDecoration(
        color: MissionPalette.dangerSoft,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: const BoxDecoration(color: MissionPalette.danger, shape: BoxShape.circle),
            child: Icon(icon, color: Colors.white, size: 18),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                color: MissionPalette.danger,
                fontSize: 13.2,
                height: 1.45,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HistoryStateBox extends StatelessWidget {
  const _HistoryStateBox({
    required this.icon,
    required this.title,
    required this.message,
    this.buttonLabel,
    this.onPressed,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? buttonLabel;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 30),
        child: Column(
          children: [
            Icon(icon, size: 50, color: OvanieColors.green),
            const SizedBox(height: 12),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: MissionPalette.navy,
                fontSize: 17,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: MissionPalette.slate,
                fontSize: 13,
                height: 1.45,
              ),
            ),
            if (buttonLabel != null && onPressed != null) ...[
              const SizedBox(height: 15),
              SizedBox(
                width: 170,
                child: MissionPrimaryButton(label: buttonLabel!, onPressed: onPressed),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
