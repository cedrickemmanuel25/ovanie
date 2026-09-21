import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import '../widgets/mission_dialogs.dart';
import '../widgets/mission_sections.dart';
import '../widgets/mission_ui.dart';
import 'mission_departure_screen.dart';

class MissionCollectingScreen extends StatefulWidget {
  const MissionCollectingScreen({
    super.key,
    required this.missionNumber,
    this.initialMission,
  });

  final String missionNumber;
  final DriverMissionDetail? initialMission;

  @override
  State<MissionCollectingScreen> createState() => _MissionCollectingScreenState();
}

class _MissionCollectingScreenState extends State<MissionCollectingScreen> {
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
      if (mounted && showSpinner) setState(() => _loading = false);
    }
  }

  Future<void> _completeCurrentPickup() async {
    final mission = _mission;
    if (mission == null || _actionBusy) return;

    final current = mission.currentPickupStop;
    final incomplete = mission.pickupStops.where((stop) => !stop.completed).length;
    setState(() => _actionBusy = true);
    try {
      DriverMissionDetail updated = mission;
      if (current != null) {
        updated = await MissionRepository.instance.completePickup(
          mission.missionNumber,
          current.id,
        );
      }

      final allCompleted = updated.pickupStops.isNotEmpty &&
          updated.pickupStops.every((stop) => stop.completed);
      if (allCompleted || (current != null && incomplete <= 1)) {
        final loaded = await MissionRepository.instance.updateStage(
          mission.missionNumber,
          'loaded',
        );
        if (!mounted) return;
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => MissionDepartureScreen(
              missionNumber: mission.missionNumber,
              initialMission: loaded,
            ),
          ),
        );
        return;
      }

      if (!mounted) return;
      setState(() => _mission = updated);
      showMissionSnack(context, 'Collecte confirmée. Passez au point suivant.');
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _reportProblem() async {
    final draft = await showMissionIncidentDialog(context);
    if (!mounted || draft == null) return;
    setState(() => _actionBusy = true);
    try {
      await MissionRepository.instance.reportIncident(
        widget.missionNumber,
        incidentType: draft.type,
        description: draft.description,
      );
      if (!mounted) return;
      showMissionSnack(context, 'Problème transmis à OVANIE Logistics.');
      await _load(showSpinner: false);
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _navigateTo(DriverMissionPickupStop stop) async {
    final coordinatesAvailable = stop.latitude != null && stop.longitude != null;
    if (coordinatesAvailable) {
      await Clipboard.setData(
        ClipboardData(text: '${stop.latitude},${stop.longitude}'),
      );
      if (mounted) {
        showMissionSnack(
          context,
          'Coordonnées de ${stop.label} copiées pour votre application GPS.',
        );
      }
      return;
    }
    await Clipboard.setData(ClipboardData(text: stop.address));
    if (mounted) {
      showMissionSnack(
        context,
        'Adresse de ${stop.label} copiée pour votre application GPS.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final mission = _mission;
    final total = mission?.pickupStops.length ?? 0;
    final completed = mission?.pickupCompletedCount ?? 0;
    final progress = total == 0 ? 0 : ((completed / total) * 100).round();
    final remaining = mission?.pickupStops.where((stop) => !stop.completed).length ?? 0;
    final actionLabel = remaining <= 1
        ? 'Confirmer le chargement terminé'
        : 'Confirmer la collecte du point';

    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Collectes en cours',
            subtitle: 'Récupération des articles',
            showBack: true,
            onBack: () => Navigator.of(context).pop(),
            trailing: const MissionStatusPill(
              label: 'En cours',
              icon: Icons.play_circle_fill_rounded,
              kind: MissionStatusKind.success,
            ),
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
                      height: 390,
                      child: Center(child: CircularProgressIndicator(color: OvanieColors.green)),
                    )
                  else if (_error != null && mission == null)
                    MissionSurfaceCard(
                      child: Column(
                        children: [
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
                    MissionOverviewBlock(
                      mission: mission,
                      progressTitle: 'Progression des collectes',
                      progressValue: progress,
                      progressTrailingLabel: '$completed collectes sur $total terminées',
                      message: 'Récupérez les articles dans l’ordre indiqué puis confirmez le chargement terminé.',
                    ),
                    const SizedBox(height: 12),
                    MissionCollectingStops(
                      stops: mission.pickupStops,
                      onNavigate: _navigateTo,
                    ),
                    const SizedBox(height: 12),
                    MissionItineraryStrip(mission: mission),
                    const SizedBox(height: 12),
                    MissionSurfaceCard(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 43,
                            height: 43,
                            decoration: const BoxDecoration(
                              color: Color(0xFFE0F7ED),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(Icons.local_shipping_rounded, color: OvanieColors.green, size: 24),
                          ),
                          const SizedBox(width: 12),
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Chargement',
                                  style: TextStyle(
                                    color: MissionPalette.navy,
                                    fontSize: 15.5,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                SizedBox(height: 2),
                                Text(
                                  'Après la dernière collecte, confirmez que tout le chargement est récupéré avant de partir vers le client.',
                                  style: TextStyle(
                                    color: MissionPalette.slate,
                                    fontSize: 12.5,
                                    height: 1.35,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          if (mission != null)
            Container(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
              decoration: const BoxDecoration(
                color: MissionPalette.mint,
                border: Border(top: BorderSide(color: MissionPalette.cardBorder)),
              ),
              child: SafeArea(
                top: false,
                child: Row(
                  children: [
                    Expanded(
                      child: MissionOutlineButton(
                        label: 'Signaler un problème',
                        loading: _actionBusy,
                        onPressed: _reportProblem,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: MissionPrimaryButton(
                        label: actionLabel,
                        loading: _actionBusy,
                        enabled: mission.currentPickupStop != null || mission.allPickupsCompleted,
                        onPressed: _completeCurrentPickup,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
