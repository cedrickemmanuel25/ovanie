import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import '../widgets/mission_dialogs.dart';
import '../widgets/mission_ui.dart';
import 'mission_accepted_screen.dart';
import 'mission_collecting_screen.dart';
import 'mission_departure_screen.dart';
import 'mission_detail_screen.dart';

class MissionListScreen extends StatefulWidget {
  const MissionListScreen({super.key});

  @override
  State<MissionListScreen> createState() => _MissionListScreenState();
}

class _MissionListScreenState extends State<MissionListScreen> {
  DriverMissionListResult? _data;
  bool _loading = true;
  String? _error;
  String _query = '';
  String _filter = 'all';
  final Set<String> _busy = <String>{};
  Timer? _liveTimer;

  @override
  void initState() {
    super.initState();
    _load();
    // L'onglet reste vivant en mémoire (IndexedStack) tant que l'app tourne :
    // sans ce polling, une mission affectée pendant que cet onglet n'était
    // pas à l'écran restait invisible ici (liste jamais rechargée), alors que
    // l'Accueil (qui sondait déjà toutes les 10s) la montrait bien.
    _liveTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      if (mounted && !_loading) _load(silent: true);
    });
  }

  @override
  void dispose() {
    _liveTimer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && !_loading && mounted) setState(() => _loading = true);
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

  List<DriverMissionSummaryModel> get _visibleMissions {
    final missions = _data?.missions ?? const <DriverMissionSummaryModel>[];
    final needle = _query.trim().toLowerCase();
    return missions.where((mission) {
      final matchesFilter = switch (_filter) {
        'to_accept' => mission.isToAccept,
        'accepted' => mission.isAccepted,
        'in_progress' => mission.isInProgress,
        'delivered' => mission.isDelivered,
        _ => !mission.isRejected && !mission.isOfferExpired,
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
    Widget screen;
    if (mission.isToAccept) {
      screen = MissionDetailScreen(missionNumber: mission.missionNumber);
    } else if (mission.isAccepted) {
      screen = MissionAcceptedScreen(missionNumber: mission.missionNumber);
    } else if (mission.isCollecting) {
      screen = MissionCollectingScreen(missionNumber: mission.missionNumber);
    } else if (mission.isLoaded || mission.isInTransit || mission.isArrived) {
      screen = MissionDepartureScreen(missionNumber: mission.missionNumber);
    } else {
      screen = MissionDetailScreen(missionNumber: mission.missionNumber);
    }

    await Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => screen),
    );
    if (mounted) await _load();
  }

  Future<void> _accept(DriverMissionSummaryModel mission) async {
    if (_busy.contains(mission.missionNumber)) return;
    setState(() => _busy.add(mission.missionNumber));
    try {
      final accepted = await MissionRepository.instance.accept(mission.missionNumber);
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => MissionAcceptedScreen(
            missionNumber: mission.missionNumber,
            initialMission: accepted,
          ),
        ),
      );
      if (mounted) await _load();
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(mission.missionNumber));
    }
  }

  Future<void> _reject(DriverMissionSummaryModel mission) async {
    final reason = await showMissionRejectDialog(context);
    if (!mounted || reason == null) return;
    setState(() => _busy.add(mission.missionNumber));
    try {
      await MissionRepository.instance.reject(mission.missionNumber, reason: reason);
      if (!mounted) return;
      showMissionSnack(context, 'Mission refusée. Elle reste disponible pour les autres livreurs éligibles.');
      await _load();
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(mission.missionNumber));
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    final visible = _visibleMissions;
    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Mes missions',
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
                  _MissionCounters(data: data),
                  const SizedBox(height: 15),
                  _MissionSearch(onChanged: (value) => setState(() => _query = value)),
                  const SizedBox(height: 12),
                  _MissionFilters(
                    selected: _filter,
                    onSelected: (value) => setState(() => _filter = value),
                  ),
                  const SizedBox(height: 12),
                  if (_loading && data == null)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 80),
                      child: Center(
                        child: CircularProgressIndicator(color: OvanieColors.green),
                      ),
                    )
                  else if (_error != null && data == null)
                    _MissionStateBox(
                      icon: Icons.cloud_off_rounded,
                      title: 'Impossible de charger les missions',
                      message: _error!,
                      buttonLabel: 'Réessayer',
                      onPressed: _load,
                    )
                  else if (visible.isEmpty)
                    const _MissionStateBox(
                      icon: Icons.assignment_turned_in_outlined,
                      title: 'Aucune mission dans cette catégorie',
                      message: 'Les missions proposées automatiquement par OVANIE Logistics apparaîtront ici.',
                    )
                  else
                    ...[
                      for (var i = 0; i < visible.length; i++) ...[
                        _MissionListCard(
                          mission: visible[i],
                          busy: _busy.contains(visible[i].missionNumber),
                          onOpen: () => _openMission(visible[i]),
                          onAccept: visible[i].isToAccept ? () => _accept(visible[i]) : null,
                          onReject: visible[i].isToAccept ? () => _reject(visible[i]) : null,
                        ),
                        if (i != visible.length - 1) const SizedBox(height: 10),
                      ],
                    ],
                  if (_loading && data != null) ...[
                    const SizedBox(height: 14),
                    const LinearProgressIndicator(
                      color: OvanieColors.green,
                      backgroundColor: Color(0xFFD9EEE5),
                      minHeight: 3,
                    ),
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

class _MissionCounters extends StatelessWidget {
  const _MissionCounters({required this.data});

  final DriverMissionListResult? data;

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
              icon: Icons.schedule_rounded,
              color: MissionPalette.amber,
              label: 'À réserver',
              value: data?.toAcceptCount ?? 0,
            ),
          ),
          const _CounterDivider(),
          Expanded(
            child: _CounterItem(
              icon: Icons.check_circle_rounded,
              color: OvanieColors.green,
              label: 'Réservées',
              value: data?.acceptedCount ?? 0,
            ),
          ),
          const _CounterDivider(),
          Expanded(
            child: _CounterItem(
              icon: Icons.play_circle_fill_rounded,
              color: OvanieColors.green,
              label: 'En cours',
              value: data?.inProgressCount ?? 0,
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
        height: 47,
        color: const Color(0xFFD5E3DE),
      );
}

class _CounterItem extends StatelessWidget {
  const _CounterItem({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final Color color;
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(icon, color: color, size: 28),
        const SizedBox(width: 9),
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: MissionPalette.slate,
                  fontSize: 11.7,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                '$value',
                style: const TextStyle(
                  color: MissionPalette.navy,
                  fontSize: 20,
                  height: 1,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MissionSearch extends StatelessWidget {
  const _MissionSearch({required this.onChanged});

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

class _MissionFilters extends StatelessWidget {
  const _MissionFilters({required this.selected, required this.onSelected});

  final String selected;
  final ValueChanged<String> onSelected;

  static const filters = <(String, String)>[
    ('all', 'Toutes'),
    ('to_accept', 'À réserver'),
    ('accepted', 'Réservées'),
    ('in_progress', 'En cours'),
    ('delivered', 'Livrées'),
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

class _MissionListCard extends StatelessWidget {
  const _MissionListCard({
    required this.mission,
    required this.busy,
    required this.onOpen,
    this.onAccept,
    this.onReject,
  });

  final DriverMissionSummaryModel mission;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback? onAccept;
  final VoidCallback? onReject;

  @override
  Widget build(BuildContext context) {
    final meta = <String>[
      missionTime(mission.pickupScheduledAt),
      '${mission.pickupCount} collecte${mission.pickupCount > 1 ? 's' : ''}',
      '${mission.itemCount} article${mission.itemCount > 1 ? 's' : ''}',
      missionWeight(mission.totalWeightKg),
    ].where((e) => e.trim().isNotEmpty && e.trim() != '—').join(' · ');
    final showPreparation = !mission.isDelivered && !mission.isInProgress && mission.preparationPercent < 100;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: mission.isToAccept ? const Color(0xFFF1D9A8) : MissionPalette.cardBorder,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      mission.missionNumber,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: MissionPalette.navy, fontSize: 15, fontWeight: FontWeight.w800),
                    ),
                  ),
                  if (mission.netAmount > 0)
                    Text(
                      missionMoney(mission.netAmount),
                      style: const TextStyle(color: OvanieColors.green, fontSize: 15, fontWeight: FontWeight.w800),
                    ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  const Icon(Icons.place_outlined, size: 16, color: MissionPalette.slate),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      mission.displayDestination,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: MissionPalette.navy, fontSize: 13.5),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                meta,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: MissionPalette.slate, fontSize: 12.5),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Flexible(child: MissionStatusPill.forMission(mission)),
                  if (showPreparation) ...[
                    const SizedBox(width: 10),
                    Expanded(
                      child: Row(
                        children: [
                          Expanded(
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(4),
                              child: LinearProgressIndicator(
                                value: mission.preparationPercent.clamp(0, 100) / 100,
                                minHeight: 4,
                                color: OvanieColors.green,
                                backgroundColor: const Color(0xFFE3EEE8),
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            '${mission.preparationPercent.clamp(0, 100)} %',
                            style: const TextStyle(color: MissionPalette.slate, fontSize: 11.5),
                          ),
                        ],
                      ),
                    ),
                  ] else
                    const Spacer(),
                  if (!mission.isToAccept) const Icon(Icons.chevron_right_rounded, color: MissionPalette.slate),
                ],
              ),
              if (mission.isToAccept) ...[
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 40,
                        child: OutlinedButton(
                          onPressed: busy ? null : onReject,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFFC62828),
                            side: const BorderSide(color: Color(0xFFF0C4C4)),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          child: const Text('Refuser', style: TextStyle(fontWeight: FontWeight.w700)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: SizedBox(
                        height: 40,
                        child: FilledButton(
                          onPressed: busy ? null : onAccept,
                          style: FilledButton.styleFrom(
                            backgroundColor: OvanieColors.green,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          child: busy
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Réserver', style: TextStyle(fontWeight: FontWeight.w700)),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _MissionStateBox extends StatelessWidget {
  const _MissionStateBox({
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
