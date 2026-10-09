import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../missions/data/mission_repository.dart';
import '../missions/models/driver_mission.dart';
import '../missions/widgets/mission_ui.dart';
import '../missions/screens/mission_detail_screen.dart';

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

  Future<void> _openMission(DriverMissionSummaryModel mission) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => MissionDetailScreen(missionNumber: mission.missionNumber),
      ),
    );
  }

  List<MapEntry<String, List<DriverMissionSummaryModel>>> _groups(List<DriverMissionSummaryModel> missions) {
    final groups = <String, List<DriverMissionSummaryModel>>{};
    for (final mission in missions) {
      groups.putIfAbsent(_dayLabel(_terminalDate(mission)), () => []).add(mission);
    }
    return groups.entries.toList();
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
            subtitle: '',
            mainPage: true,
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
                    for (final group in _groups(visible)) ...[
                      Padding(
                        padding: const EdgeInsets.fromLTRB(2, 6, 2, 8),
                        child: Text(
                          group.key,
                          style: const TextStyle(color: MissionPalette.slate, fontSize: 13, fontWeight: FontWeight.w600),
                        ),
                      ),
                      Container(
                        clipBehavior: Clip.antiAlias,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: MissionPalette.cardBorder),
                        ),
                        child: Column(
                          children: [
                            for (var i = 0; i < group.value.length; i++)
                              _HistoryCard(
                                mission: group.value[i],
                                onOpen: () => _openMission(group.value[i]),
                                last: i == group.value.length - 1,
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
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
  const _HistoryCard({required this.mission, required this.onOpen, required this.last});

  final DriverMissionSummaryModel mission;
  final VoidCallback onOpen;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final (IconData icon, Color color, String label) = mission.isDelivered
        ? (Icons.check_rounded, OvanieColors.green, 'Livrée')
        : mission.hasIncident
            ? (Icons.warning_amber_rounded, MissionPalette.amber, 'Incident')
            : mission.isRejected
                ? (Icons.close_rounded, MissionPalette.danger, 'Refusée')
                : (Icons.history_rounded, MissionPalette.slate, 'Expirée');
    final end = _terminalDate(mission);
    return InkWell(
      onTap: onOpen,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          border: last ? null : const Border(bottom: BorderSide(color: MissionPalette.cardBorder)),
        ),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(color: color.withValues(alpha: .12), shape: BoxShape.circle),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    mission.missionNumber,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: MissionPalette.navy, fontSize: 14.5, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    mission.isRejected && (mission.rejectionReason ?? '').trim().isNotEmpty
                        ? 'Motif : ${mission.rejectionReason}'
                        : mission.displayDestination,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: MissionPalette.slate, fontSize: 13),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  mission.isDelivered && mission.netAmount > 0 ? missionMoney(mission.netAmount) : label,
                  style: TextStyle(
                    color: mission.isDelivered ? MissionPalette.navy : color,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  end == null ? '' : missionTime(end),
                  style: const TextStyle(color: MissionPalette.slate, fontSize: 12),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

DateTime? _terminalDate(DriverMissionSummaryModel mission) =>
    (mission.deliveredAt ?? mission.rejectedAt ?? mission.pickupScheduledAt)?.toLocal();

String _dayLabel(DateTime? date) {
  if (date == null) return 'Sans date';
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final day = DateTime(date.year, date.month, date.day);
  final diff = today.difference(day).inDays;
  if (diff == 0) return 'Aujourd’hui';
  if (diff == 1) return 'Hier';
  const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  return '${date.day} ${months[date.month - 1]}${date.year == now.year ? '' : ' ${date.year}'}';
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
