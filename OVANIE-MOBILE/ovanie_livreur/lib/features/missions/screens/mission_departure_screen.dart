import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';

import '../../../app/theme.dart';
import '../../../core/location/driver_presence_service.dart';
import '../../../core/network/api_client.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import '../widgets/mission_dialogs.dart';
import '../widgets/mission_sections.dart';
import '../widgets/mission_ui.dart';

/// Étape finale de la mission OVANIE Logistics : livreur -> client.
///
/// Le GPS de mission n'est envoyé qu'après le départ réel vers le client.
/// L'OTP n'est jamais exposé au livreur : le client le communique après la
/// remise complète des articles.
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
  bool _handoverConfirmed = false;
  bool _sendingLocation = false;
  String? _error;
  DateTime? _lastLocationSentAt;
  final _otpController = TextEditingController();
  StreamSubscription<Position>? _positionSubscription;
  Timer? _poller;

  @override
  void initState() {
    super.initState();
    _mission = widget.initialMission;
    _otpController.addListener(_otpChanged);
    _load(showSpinner: _mission == null);
    _startLiveTracking();
    _poller = Timer.periodic(const Duration(seconds: 15), (_) {
      final mission = _mission;
      if (mounted && !_actionBusy && mission != null && mission.isInTransit) {
        _load(showSpinner: false, silent: true);
      }
    });
  }

  @override
  void dispose() {
    _poller?.cancel();
    _positionSubscription?.cancel();
    _otpController.removeListener(_otpChanged);
    _otpController.dispose();
    super.dispose();
  }

  void _otpChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _startLiveTracking() async {
    try {
      await DriverPresenceService.instance.start();
      await _positionSubscription?.cancel();
      _positionSubscription = DriverPresenceService.instance.positions.listen(
        _onStablePosition,
        onError: (_) {},
      );

      final stable = DriverPresenceService.instance.stablePosition;
      if (stable != null) {
        await _onStablePosition(stable, force: true);
      }
    } catch (_) {
      // Le parcours reste utilisable en mode manuel. L'onglet Suivi permet
      // déjà de signaler un GPS indisponible à OVANIE Logistics.
    }
  }

  Future<void> _onStablePosition(Position position, {bool force = false}) async {
    final mission = _mission;
    if (mission == null || !mission.isInTransit || _sendingLocation) return;

    final now = DateTime.now();
    if (!force &&
        _lastLocationSentAt != null &&
        now.difference(_lastLocationSentAt!) < const Duration(seconds: 8)) {
      return;
    }

    _sendingLocation = true;
    try {
      await MissionRepository.instance.recordLocation(
        mission.missionNumber,
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy.isFinite ? position.accuracy : null,
        speed: position.speed.isFinite ? position.speed : null,
        heading: position.heading.isFinite ? position.heading : null,
      );
      _lastLocationSentAt = now;
    } catch (_) {
      // Une coupure réseau ponctuelle ne bloque jamais la livraison.
    } finally {
      _sendingLocation = false;
    }
  }

  Future<void> _load({bool showSpinner = true, bool silent = false}) async {
    if (showSpinner && mounted) setState(() => _loading = true);
    try {
      final mission = await MissionRepository.instance.fetchMission(widget.missionNumber);
      if (!mounted) return;
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

      final stable = DriverPresenceService.instance.stablePosition;
      if (stable != null) {
        await _onStablePosition(stable, force: true);
      }
      if (mounted) {
        showMissionSnack(
          context,
          'Livraison démarrée. Le client peut maintenant suivre votre progression.',
        );
      }
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _confirmArrival() async {
    final mission = _mission;
    if (mission == null || _actionBusy || !mission.isInTransit) return;
    setState(() => _actionBusy = true);
    try {
      final stable = DriverPresenceService.instance.stablePosition;
      if (stable != null) {
        await _onStablePosition(stable, force: true);
      }

      final updated = await MissionRepository.instance.updateStage(
        mission.missionNumber,
        'arrived',
      );
      if (!mounted) return;
      setState(() => _mission = updated);
      showMissionSnack(
        context,
        'Arrivée confirmée. Remettez tous les articles avant de demander le code au client.',
      );
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _confirmDelivery() async {
    final mission = _mission;
    final code = _otpController.text.trim();
    if (mission == null ||
        _actionBusy ||
        !mission.isArrived ||
        !_handoverConfirmed ||
        code.length != 6) {
      return;
    }

    setState(() => _actionBusy = true);
    try {
      final delivered = await MissionRepository.instance.verifyOtp(
        mission.missionNumber,
        code,
        handoverConfirmed: _handoverConfirmed,
      );
      if (!mounted) return;
      _otpController.clear();
      setState(() => _mission = delivered);
      showMissionSnack(context, 'Livraison confirmée. La mission est terminée.');
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

  Future<void> _copyDestination() async {
    final mission = _mission;
    if (mission == null) return;
    await Clipboard.setData(ClipboardData(text: mission.destinationAddress ?? mission.displayDestination));
    if (mounted) showMissionSnack(context, 'Adresse client copiée.');
  }

  @override
  Widget build(BuildContext context) {
    final mission = _mission;
    final route = mission?.routePlan;
    final delivered = mission?.isDelivered ?? false;

    String primaryLabel = 'Démarrer la livraison client';
    VoidCallback? primaryAction = _startTrip;
    var primaryEnabled = mission?.isLoaded ?? false;

    if (mission != null && mission.isDelivered) {
      primaryLabel = 'Terminer';
      primaryAction = () => Navigator.of(context).pop();
      primaryEnabled = true;
    } else if (mission != null && mission.isArrived) {
      primaryLabel = 'Confirmer la remise';
      primaryAction = _confirmDelivery;
      primaryEnabled = _handoverConfirmed && _otpController.text.trim().length == 6;
    } else if (mission != null && mission.isInTransit) {
      primaryLabel = 'Je suis arrivé chez le client';
      primaryAction = _confirmArrival;
      primaryEnabled = true;
    }

    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: delivered ? 'Livraison terminée' : 'Livraison client',
            subtitle: delivered
                ? 'Mission clôturée avec confirmation client'
                : 'Dernière étape de la mission OVANIE',
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
                      progressTitle: 'Collecte vendeurs',
                      progressValue: 100,
                      progressCompletedText: 'Tous les articles ont été récupérés',
                      message: _missionMessage(mission),
                      messageWarning: mission.isArrived,
                    ),
                    if (mission.incidentType != null) ...[
                      const SizedBox(height: 12),
                      MissionTintMessage(
                        text: 'Incident en cours : ${incidentTypeLabel(mission.incidentType)}${(mission.incidentDescription ?? '').trim().isNotEmpty ? ' — ${mission.incidentDescription}' : ''}. OVANIE Logistics suit le dossier.',
                        warning: true,
                        icon: Icons.warning_amber_rounded,
                      ),
                    ],
                    const SizedBox(height: 12),
                    _DeliveryProgressCard(mission: mission),
                    const SizedBox(height: 12),
                    MissionSurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const MissionSectionTitle('Destination client'),
                          const SizedBox(height: 12),
                          MissionDestinationBox(
                            destination: mission.displayDestination,
                            subtitle: 'Adresse finale de livraison',
                          ),
                          const SizedBox(height: 10),
                          MissionOutlineButton(
                            label: 'Copier l’adresse',
                            onPressed: _copyDestination,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    MissionSurfaceCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Row(
                            children: [
                              const Expanded(child: MissionSectionTitle('Trajet vers le client')),
                              if (mission.isInTransit)
                                const _LiveTrackingBadge(),
                            ],
                          ),
                          const SizedBox(height: 12),
                          MissionRouteMap(
                            route: route,
                            points: mission.mapPoints,
                            height: 205,
                          ),
                          const SizedBox(height: 11),
                          _RouteMetrics(route: route),
                          if (mission.isInTransit) ...[
                            const SizedBox(height: 12),
                            MissionTintMessage(
                              text: mission.gpsStatus == 'unavailable'
                                  ? 'Le GPS de mission est signalé indisponible. La livraison reste utilisable avec les étapes manuelles.'
                                  : 'Suivi GPS actif : OVANIE Logistics et le suivi client reçoivent votre progression pendant le trajet.',
                              warning: mission.gpsStatus == 'unavailable',
                              icon: mission.gpsStatus == 'unavailable'
                                  ? Icons.gps_off_rounded
                                  : Icons.gps_fixed_rounded,
                            ),
                          ],
                        ],
                      ),
                    ),
                    if (mission.isArrived) ...[
                      const SizedBox(height: 12),
                      _CustomerHandoverCard(
                        controller: _otpController,
                        handoverConfirmed: _handoverConfirmed,
                        onChanged: (value) => setState(() => _handoverConfirmed = value),
                      ),
                    ],
                    if (mission.isDelivered) ...[
                      const SizedBox(height: 12),
                      const MissionSurfaceCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Icon(Icons.verified_rounded, size: 46, color: OvanieColors.green),
                            SizedBox(height: 10),
                            Text(
                              'Livraison confirmée',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: MissionPalette.navy,
                                fontSize: 19,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            SizedBox(height: 7),
                            Text(
                              'Le client a confirmé la remise avec son code de sécurité. Vous êtes de nouveau disponible pour une prochaine mission.',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: MissionPalette.slate, height: 1.45),
                            ),
                          ],
                        ),
                      ),
                    ],
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
                    if (!mission.isDelivered) ...[
                      Expanded(
                        child: MissionOutlineButton(
                          label: 'Signaler un problème',
                          loading: _actionBusy,
                          onPressed: _reportProblem,
                        ),
                      ),
                      const SizedBox(width: 12),
                    ],
                    Expanded(
                      child: MissionPrimaryButton(
                        label: primaryLabel,
                        loading: _actionBusy,
                        enabled: primaryEnabled,
                        onPressed: primaryAction,
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

  String _missionMessage(DriverMissionDetail mission) {
    if (mission.isDelivered) {
      return 'La remise au client a été confirmée avec son code de sécurité. La mission est terminée.';
    }
    if (mission.isArrived) {
      return 'Vous êtes arrivé chez le client. Remettez tous les articles, puis demandez son code à 6 chiffres.';
    }
    if (mission.isInTransit) {
      return 'Vous êtes en route vers le client. Le suivi de mission est actif jusqu’à votre arrivée.';
    }
    return 'Tous les retraits vendeurs sont terminés. Vérifiez le chargement puis démarrez la livraison client.';
  }
}

class _DeliveryProgressCard extends StatelessWidget {
  const _DeliveryProgressCard({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    final travelling = mission.isInTransit || mission.isArrived || mission.isDelivered;
    final arrived = mission.isArrived || mission.isDelivered;
    final delivered = mission.isDelivered;

    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const MissionSectionTitle('Étapes de livraison'),
          const SizedBox(height: 14),
          _DeliveryStep(
            title: 'Chargement terminé',
            subtitle: 'Tous les points vendeurs ont été collectés',
            done: true,
            active: mission.isLoaded,
          ),
          _DeliveryStep(
            title: 'En route vers le client',
            subtitle: 'Suivi de la progression et ETA',
            done: travelling,
            active: mission.isInTransit,
          ),
          _DeliveryStep(
            title: 'Arrivée chez le client',
            subtitle: 'Remise complète des articles',
            done: arrived,
            active: mission.isArrived,
          ),
          _DeliveryStep(
            title: 'Confirmation client',
            subtitle: 'Code de sécurité à 6 chiffres',
            done: delivered,
            active: mission.isArrived,
            last: true,
          ),
        ],
      ),
    );
  }
}

class _DeliveryStep extends StatelessWidget {
  const _DeliveryStep({
    required this.title,
    required this.subtitle,
    required this.done,
    required this.active,
    this.last = false,
  });

  final String title;
  final String subtitle;
  final bool done;
  final bool active;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final color = done || active ? OvanieColors.green : MissionPalette.slate;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 32,
            child: Column(
              children: [
                Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    color: done ? OvanieColors.green : Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: color, width: 2),
                  ),
                  child: Icon(
                    done ? Icons.check_rounded : (active ? Icons.circle : Icons.circle_outlined),
                    size: done ? 16 : 9,
                    color: done ? Colors.white : color,
                  ),
                ),
                if (!last)
                  Expanded(
                    child: Container(width: 2, color: done ? OvanieColors.green : MissionPalette.cardBorder),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: last ? 0 : 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: active ? MissionPalette.navy : MissionPalette.slate,
                      fontSize: 13.5,
                      fontWeight: active || done ? FontWeight.w800 : FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: const TextStyle(color: MissionPalette.slate, fontSize: 11.5, height: 1.3),
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

class _CustomerHandoverCard extends StatelessWidget {
  const _CustomerHandoverCard({
    required this.controller,
    required this.handoverConfirmed,
    required this.onChanged,
  });

  final TextEditingController controller;
  final bool handoverConfirmed;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const MissionSectionTitle('Remise au client'),
          const SizedBox(height: 12),
          const MissionTintMessage(
            text: 'Le code appartient au client. Ne le demandez qu’après avoir remis tous les articles de la mission.',
            icon: Icons.verified_user_rounded,
          ),
          const SizedBox(height: 12),
          CheckboxListTile(
            value: handoverConfirmed,
            onChanged: (value) => onChanged(value == true),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            activeColor: OvanieColors.green,
            title: const Text(
              'Tous les articles ont été remis au client',
              style: TextStyle(
                color: MissionPalette.navy,
                fontSize: 13.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            subtitle: const Text(
              'Je confirme la remise complète avant validation du code.',
              style: TextStyle(color: MissionPalette.slate, fontSize: 11.7),
            ),
          ),
          const SizedBox(height: 6),
          TextField(
            controller: controller,
            enabled: handoverConfirmed,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            textAlign: TextAlign.center,
            maxLength: 6,
            style: const TextStyle(
              color: MissionPalette.navy,
              fontSize: 26,
              fontWeight: FontWeight.w900,
              letterSpacing: 10,
            ),
            decoration: InputDecoration(
              counterText: '',
              hintText: '000000',
              labelText: 'Code client à 6 chiffres',
              border: const OutlineInputBorder(),
              filled: true,
              fillColor: handoverConfirmed ? Colors.white : MissionPalette.mintStrong,
            ),
          ),
        ],
      ),
    );
  }
}

class _LiveTrackingBadge extends StatelessWidget {
  const _LiveTrackingBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: OvanieColors.green.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(999),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.circle, size: 7, color: OvanieColors.green),
          SizedBox(width: 5),
          Text(
            'Suivi actif',
            style: TextStyle(color: OvanieColors.green, fontSize: 10.5, fontWeight: FontWeight.w800),
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
    final distance = route?.distanceKm;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: BoxDecoration(
        color: MissionPalette.mintStrong,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(child: _Metric(label: 'Distance', value: distance == null ? '—' : '${distance.toStringAsFixed(1)} km')),
          Expanded(child: _Metric(label: 'Durée', value: duration == null ? '—' : '$duration min')),
          Expanded(child: _Metric(label: 'Arrivée', value: arrivalText)),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(label, style: const TextStyle(color: MissionPalette.slate, fontSize: 10.5)),
        const SizedBox(height: 3),
        Text(
          value,
          textAlign: TextAlign.center,
          style: const TextStyle(color: MissionPalette.navy, fontSize: 12.2, fontWeight: FontWeight.w800),
        ),
      ],
    );
  }
}
