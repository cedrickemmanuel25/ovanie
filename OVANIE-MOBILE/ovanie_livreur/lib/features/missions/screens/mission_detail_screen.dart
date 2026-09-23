import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import '../widgets/mission_dialogs.dart';
import '../widgets/mission_sections.dart';
import '../widgets/mission_ui.dart';
import 'mission_accepted_screen.dart';

class MissionDetailScreen extends StatefulWidget {
  const MissionDetailScreen({
    super.key,
    required this.missionNumber,
    this.initialMission,
  });

  final String missionNumber;
  final DriverMissionDetail? initialMission;

  @override
  State<MissionDetailScreen> createState() => _MissionDetailScreenState();
}

class _MissionDetailScreenState extends State<MissionDetailScreen> {
  DriverMissionDetail? _mission;
  bool _loading = false;
  bool _actionBusy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _mission = widget.initialMission;
    _load(showSpinner: _mission == null);
  }

  Future<void> _load({bool showSpinner = true}) async {
    if (showSpinner && mounted) setState(() => _loading = true);
    try {
      final mission = await MissionRepository.instance.fetchMission(widget.missionNumber);
      if (!mounted) return;
      setState(() {
        _mission = mission;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _accept() async {
    if (_actionBusy) return;
    setState(() => _actionBusy = true);
    try {
      final mission = await MissionRepository.instance.accept(widget.missionNumber);
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => MissionAcceptedScreen(
            missionNumber: widget.missionNumber,
            initialMission: mission,
          ),
        ),
      );
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _reject() async {
    final reason = await showMissionRejectDialog(context);
    if (!mounted || reason == null) return;
    setState(() => _actionBusy = true);
    try {
      await MissionRepository.instance.reject(widget.missionNumber, reason: reason);
      if (!mounted) return;
      showMissionSnack(context, 'Mission refusée. Elle reste disponible pour les autres livreurs éligibles.');
      Navigator.of(context).pop();
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final mission = _mission;
    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Détail de mission',
            subtitle: mission == null
                ? 'Mission'
                : mission.isToAccept
                    ? 'Mission à réserver'
                    : mission.isDelivered
                        ? 'Mission livrée'
                        : mission.statusLabel,
            showBack: true,
            onBack: () => Navigator.of(context).pop(),
            trailing: mission == null ? null : MissionStatusPill.forMission(mission),
          ),
          Expanded(
            child: RefreshIndicator(
              color: OvanieColors.green,
              onRefresh: () => _load(showSpinner: false),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                children: [
                  if (_loading && mission == null)
                    const SizedBox(
                      height: 420,
                      child: Center(child: CircularProgressIndicator(color: OvanieColors.green)),
                    )
                  else if (_error != null && mission == null)
                    MissionSurfaceCard(
                      child: Column(
                        children: [
                          const Icon(Icons.cloud_off_rounded, size: 45, color: OvanieColors.green),
                          const SizedBox(height: 12),
                          Text(
                            _error!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: MissionPalette.slate, height: 1.4),
                          ),
                          const SizedBox(height: 14),
                          MissionPrimaryButton(label: 'Réessayer', onPressed: () => _load()),
                        ],
                      ),
                    )
                  else if (mission != null) ...[
                    MissionOverviewBlock(mission: mission),
                    if (mission.isToAccept) ...[
                      const SizedBox(height: 12),
                      const MissionTintMessage(
                        text: 'Réserver cette mission confirme votre engagement. Vous ne partez pas immédiatement : OVANIE vous donnera le signal lorsque tous les vendeurs seront prêts.',
                        warning: true,
                        icon: Icons.lock_clock_rounded,
                      ),
                    ],
                    const SizedBox(height: 12),
                    if (mission.isToAccept && mission.netAmount > 0) ...[
                      _EarningsCard(netAmount: mission.netAmount),
                      const SizedBox(height: 12),
                    ],
                    MissionPreparationStops(stops: mission.pickupStops, title: 'Collectes prévues'),
                    const SizedBox(height: 12),
                    _DeliveryPreview(mission: mission),
                  ],
                ],
              ),
            ),
          ),
          if (mission != null && mission.isToAccept)
            _BottomActions(
              busy: _actionBusy,
              onReject: _reject,
              onAccept: _accept,
            ),
        ],
      ),
    );
  }
}

class _DeliveryPreview extends StatelessWidget {
  const _DeliveryPreview({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const MissionSectionTitle('Livraison'),
          const SizedBox(height: 13),
          MissionRouteMap(
            route: mission.routePlan,
            points: mission.mapPoints,
            height: 178,
          ),
          const SizedBox(height: 10),
          MissionDestinationBox(
            destination: mission.displayDestination,
            subtitle: 'Livraison client',
          ),
          const SizedBox(height: 10),
          const MissionTintMessage(
            text: 'Après réservation, la collecte restera verrouillée jusqu’à ce que tous les points vendeurs soient prêts.',
            icon: Icons.info_rounded,
          ),
        ],
      ),
    );
  }
}

class _EarningsCard extends StatelessWidget {
  const _EarningsCard({required this.netAmount});

  final double netAmount;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF9F3),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFBFEBD9)),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: const BoxDecoration(color: OvanieColors.green, shape: BoxShape.circle),
            child: const Icon(Icons.payments_rounded, color: Colors.white, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Gain prévu',
                  style: TextStyle(
                    color: MissionPalette.slate,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  missionMoney(netAmount),
                  style: const TextStyle(
                    color: MissionPalette.navy,
                    fontSize: 21,
                    fontWeight: FontWeight.w900,
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

class _BottomActions extends StatelessWidget {
  const _BottomActions({
    required this.busy,
    required this.onReject,
    required this.onAccept,
  });

  final bool busy;
  final VoidCallback onReject;
  final VoidCallback onAccept;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: MissionPalette.cardBorder)),
      ),
      child: SafeArea(
        top: false,
        child: Row(
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
                label: 'Réserver la mission',
                loading: busy,
                onPressed: onAccept,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
