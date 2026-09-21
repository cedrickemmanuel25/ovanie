import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import '../widgets/mission_dialogs.dart';
import '../widgets/mission_sections.dart';
import '../widgets/mission_ui.dart';

class MissionDepartureScreen extends StatefulWidget {
  const MissionDepartureScreen({
    super.key,
    required this.missionNumber,
    this.initialMission,
  });

  final String missionNumber;
  final DriverMissionDetail? initialMission;

  @override
  State<MissionDepartureScreen> createState() => _MissionDepartureScreenState();
}

class _MissionDepartureScreenState extends State<MissionDepartureScreen> {
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

  Future<void> _startTrip() async {
    final mission = _mission;
    if (mission == null || _actionBusy || !mission.isLoaded) return;
    setState(() => _actionBusy = true);
    try {
      final updated = await MissionRepository.instance.updateStage(
        mission.missionNumber,
        'in_transit',
      );
      if (!mounted) return;
      setState(() => _mission = updated);
      showMissionSnack(context, 'Trajet vers le client démarré.');
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
    final route = mission?.routePlan;
    final alreadyStarted = mission != null && (mission.isInTransit || mission.isArrived);
    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Départ vers le client',
            subtitle: 'Trajet vers la destination finale',
            showBack: true,
            onBack: () => Navigator.of(context).pop(),
            trailing: mission == null
                ? null
                : MissionStatusPill.forMission(mission),
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
                      progressTitle: 'Préparation / chargement',
                      progressValue: 100,
                      progressCompletedText: 'Collectes terminées et chargement confirmé',
                      message: 'Tous les points de collecte sont terminés et le chargement est confirmé. '
                          'Vous pouvez maintenant prendre la route vers le client.',
                    ),
                    const SizedBox(height: 12),
                    MissionSurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const MissionSectionTitle('Destination finale'),
                          const SizedBox(height: 12),
                          MissionDestinationBox(
                            destination: mission.displayDestination,
                            subtitle: 'Livraison client',
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    MissionSurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const MissionSectionTitle('Trajet vers le client'),
                          const SizedBox(height: 12),
                          MissionRouteMap(
                            route: route,
                            points: mission.mapPoints,
                            height: 190,
                          ),
                          const SizedBox(height: 11),
                          _RouteMetrics(route: route),
                          const SizedBox(height: 13),
                          const Text(
                            'Rappel avant départ',
                            style: TextStyle(
                              color: MissionPalette.slate,
                              fontSize: 12.3,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 8),
                          const _ReminderLine(text: 'Vérifier que tout le chargement est sécurisé'),
                          const SizedBox(height: 6),
                          const _ReminderLine(text: 'Démarrer la navigation vers le client'),
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
                        label: alreadyStarted ? 'Trajet démarré' : 'Démarrer le trajet vers le client',
                        loading: _actionBusy,
                        enabled: mission.isLoaded,
                        onPressed: _startTrip,
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

class _RouteMetrics extends StatelessWidget {
  const _RouteMetrics({required this.route});

  final DriverMissionRoutePlan? route;

  @override
  Widget build(BuildContext context) {
    final arrival = route?.arrivalAt;
    final arrivalText = arrival == null ? '—' : missionTime(arrival);
    final duration = route?.durationMinutes;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF9F3),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(
            child: _RouteMetric(
              icon: Icons.route_rounded,
              label: 'Distance estimée',
              value: missionDistance(route?.distanceKm),
            ),
          ),
          const _MetricDivider(),
          Expanded(
            child: _RouteMetric(
              icon: Icons.schedule_rounded,
              label: 'Temps estimé',
              value: duration == null ? '—' : '$duration min',
            ),
          ),
          const _MetricDivider(),
          Expanded(
            child: _RouteMetric(
              icon: Icons.flag_rounded,
              label: 'Arrivée estimée',
              value: arrivalText,
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricDivider extends StatelessWidget {
  const _MetricDivider();

  @override
  Widget build(BuildContext context) => Container(
        width: 1,
        height: 43,
        color: const Color(0xFFD0E4DC),
      );
}

class _RouteMetric extends StatelessWidget {
  const _RouteMetric({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(icon, color: MissionPalette.slate, size: 24),
        const SizedBox(width: 7),
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
                  fontSize: 10.2,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: MissionPalette.navy,
                  fontSize: 13.2,
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

class _ReminderLine extends StatelessWidget {
  const _ReminderLine({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Icon(Icons.check_circle_rounded, color: OvanieColors.green, size: 21),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: OvanieColors.greenDark,
              fontSize: 12.2,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}
