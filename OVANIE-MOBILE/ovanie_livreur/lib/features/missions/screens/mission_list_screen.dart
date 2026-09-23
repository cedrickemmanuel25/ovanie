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
            subtitle: 'Missions disponibles, réservées et en cours',
            mainPage: true,
            trailing: MissionNotificationButton(
              count: data?.unreadNotifications ?? 0,
              onTap: () => showMissionSnack(
                context,
                data?.unreadNotifications == 0
                    ? 'Aucune nouvelle notification.'
                    : '${data?.unreadNotifications ?? 0} notification(s) non lue(s).',
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
                        if (i != visible.length - 1) const SizedBox(height: 12),
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
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: mission.isToAccept ? const Color(0xFFF5E2BD) : MissionPalette.cardBorder,
        ),
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
                constraints: const BoxConstraints(maxWidth: 155),
                child: MissionStatusPill.forMission(mission),
              ),
            ],
          ),
          const SizedBox(height: 13),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 6,
                child: Column(
                  children: [
                    if (mission.netAmount > 0)
                      MissionInfoRow(
                        icon: Icons.payments_rounded,
                        label: 'Vous gagnez',
                        labelWidth: 72,
                        value: missionMoney(mission.netAmount),
                      ),
                    MissionInfoRow(
                      icon: Icons.schedule_rounded,
                      label: 'Heure prévue',
                      labelWidth: 72,
                      value: missionTime(mission.pickupScheduledAt),
                    ),
                    MissionInfoRow(
                      icon: Icons.location_on_rounded,
                      label: 'Destination',
                      labelWidth: 72,
                      value: mission.displayDestination,
                    ),
                    MissionInfoRow(
                      icon: Icons.inventory_2_outlined,
                      label: 'Collectes',
                      labelWidth: 72,
                      value: '${mission.pickupCount} point${mission.pickupCount > 1 ? 's' : ''} de collecte',
                    ),
                  ],
                ),
              ),
              Container(
                width: 1,
                height: 84,
                margin: const EdgeInsets.symmetric(horizontal: 7, vertical: 5),
                color: const Color(0xFFDDE4EC),
              ),
              Expanded(
                flex: 4,
                child: Column(
                  children: [
                    MissionInfoRow(
                      icon: Icons.scale_outlined,
                      label: 'Poids',
                      labelWidth: 46,
                      value: missionWeight(mission.totalWeightKg),
                    ),
                    MissionInfoRow(
                      icon: Icons.local_shipping_rounded,
                      label: 'Véhicule',
                      labelWidth: 46,
                      value: mission.vehicleLabel ?? '—',
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          const Text(
            'Préparation de la commande',
            style: TextStyle(
              color: MissionPalette.slate,
              fontSize: 12.5,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          MissionProgress(value: mission.preparationPercent),
          const SizedBox(height: 11),
          if (mission.isOffered)
            const MissionTintMessage(
              text: 'Vous pouvez réserver cette mission maintenant. La collecte restera bloquée tant que les vendeurs ne sont pas prêts.',
              warning: true,
              icon: Icons.info_outline_rounded,
            )
          else if (mission.isAccepted)
            MissionTintMessage(
              text: mission.canStart
                  ? 'Tous les vendeurs sont prêts — la collecte peut démarrer.'
                  : 'Mission réservée — ${mission.preparationLabel}. Attendez le signal de collecte.',
              warning: !mission.canStart,
              icon: mission.canStart ? Icons.check_circle_rounded : Icons.hourglass_top_rounded,
            )
          else if (mission.isCollecting || mission.isInProgress)
            MissionTintMessage(
              text: mission.statusLabel.trim().isEmpty ? 'Mission en cours' : mission.statusLabel,
              icon: Icons.play_arrow_rounded,
            ),
          const SizedBox(height: 10),
          if (mission.isToAccept)
            Row(
              children: [
                Expanded(
                  child: MissionOutlineButton(
                    label: 'Refuser',
                    danger: true,
                    loading: busy,
                    onPressed: onReject,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: MissionPrimaryButton(
                    label: 'Réserver',
                    loading: busy,
                    onPressed: onAccept,
                  ),
                ),
              ],
            )
          else
            mission.isInProgress
                ? MissionPrimaryButton(
                    label: 'Suivre la mission',
                    onPressed: onOpen,
                  )
                : MissionOutlineButton(
                    label: 'Voir la mission',
                    onPressed: onOpen,
                  ),
        ],
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
