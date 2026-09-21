import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import '../widgets/mission_dialogs.dart';
import '../widgets/mission_sections.dart';
import '../widgets/mission_ui.dart';
import 'mission_collecting_screen.dart';

class MissionAcceptedScreen extends StatefulWidget {
  const MissionAcceptedScreen({
    super.key,
    required this.missionNumber,
    this.initialMission,
  });

  final String missionNumber;
  final DriverMissionDetail? initialMission;

  @override
  State<MissionAcceptedScreen> createState() => _MissionAcceptedScreenState();
}

class _MissionAcceptedScreenState extends State<MissionAcceptedScreen> {
  DriverMissionDetail? _mission;
  bool _loading = false;
  bool _actionBusy = false;
  String? _error;
  Timer? _poller;

  @override
  void initState() {
    super.initState();
    _mission = widget.initialMission;
    _load(showSpinner: _mission == null);
    _poller = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted && !_actionBusy) _load(showSpinner: false, silent: true);
    });
  }

  @override
  void dispose() {
    _poller?.cancel();
    super.dispose();
  }

  Future<void> _load({bool showSpinner = true, bool silent = false}) async {
    if (showSpinner && mounted) setState(() => _loading = true);
    try {
      final mission = await MissionRepository.instance.fetchMission(widget.missionNumber);
      if (!mounted) return;
      if (!mission.isAccepted && mission.isCollecting) {
        _poller?.cancel();
      }
      setState(() {
        _mission = mission;
        if (!silent) _error = null;
      });
    } catch (error) {
      if (!mounted || silent) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted && showSpinner) setState(() => _loading = false);
    }
  }

  Future<void> _start() async {
    final mission = _mission;
    if (mission == null || mission.preparationPercent < 100 || _actionBusy) return;
    setState(() => _actionBusy = true);
    try {
      final started = await MissionRepository.instance.start(widget.missionNumber);
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => MissionCollectingScreen(
            missionNumber: widget.missionNumber,
            initialMission: started,
          ),
        ),
      );
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

  @override
  Widget build(BuildContext context) {
    final mission = _mission;
    final canStart = mission != null && mission.preparationPercent >= 100 && mission.isAccepted;
    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Mission acceptée',
            subtitle: 'Mission réservée au livreur',
            showBack: true,
            onBack: () => Navigator.of(context).pop(),
            trailing: mission == null
                ? null
                : const MissionStatusPill(
                    label: 'Acceptée',
                    icon: Icons.check_circle_rounded,
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
                      message: mission.preparationPercent < 100
                          ? 'La préparation des articles est en cours. Cette mission vous est réservée. '
                              'Vous pourrez démarrer les collectes dès que la préparation atteindra 100 %.'
                          : 'Tous les points sont prêts. Vous pouvez démarrer les collectes.',
                      messageWarning: mission.preparationPercent < 100,
                    ),
                    const SizedBox(height: 12),
                    MissionPreparationStops(stops: mission.pickupStops),
                    const SizedBox(height: 12),
                    MissionSurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const MissionSectionTitle('Livraison client'),
                          const SizedBox(height: 12),
                          MissionDestinationBox(destination: mission.displayDestination),
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
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    MissionPrimaryButton(
                      label: 'Démarrer les collectes',
                      icon: Icons.play_arrow_rounded,
                      enabled: canStart,
                      loading: _actionBusy,
                      onPressed: _start,
                    ),
                    if (!canStart && mission.isAccepted) ...[
                      const SizedBox(height: 3),
                      Text(
                        'Disponible lorsque la préparation atteint 100 %',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: MissionPalette.slate.withValues(alpha: .9),
                          fontSize: 11.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    const SizedBox(height: 9),
                    MissionOutlineButton(
                      label: 'Signaler un problème',
                      loading: _actionBusy,
                      onPressed: _reportProblem,
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
