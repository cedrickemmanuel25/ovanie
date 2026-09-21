import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../missions/data/mission_repository.dart';
import '../missions/models/driver_mission.dart';
import '../missions/widgets/mission_ui.dart';

/// Détail d'une mission archivée (livrée, incident ou refusée).
class MissionHistoryDetailScreen extends StatefulWidget {
  const MissionHistoryDetailScreen({
    super.key,
    required this.missionNumber,
    this.initialMission,
  });

  final String missionNumber;
  final DriverMissionDetail? initialMission;

  @override
  State<MissionHistoryDetailScreen> createState() => _MissionHistoryDetailScreenState();
}

class _MissionHistoryDetailScreenState extends State<MissionHistoryDetailScreen> {
  DriverMissionDetail? _mission;
  bool _loading = false;
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

  @override
  Widget build(BuildContext context) {
    final mission = _mission;
    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Détail de mission',
            subtitle: 'Mission archivée',
            showBack: true,
            onBack: () => Navigator.of(context).pop(),
          ),
          Expanded(
            child: RefreshIndicator(
              color: OvanieColors.green,
              onRefresh: () => _load(showSpinner: false),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 22),
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
                    _HeaderCard(mission: mission),
                    const SizedBox(height: 12),
                    _SummaryCard(mission: mission),
                    const SizedBox(height: 12),
                    _StepsCard(mission: mission),
                    if (mission.pickupStops.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      _PickupStopsCard(mission: mission),
                    ],
                    const SizedBox(height: 12),
                    _RouteCard(mission: mission),
                    const SizedBox(height: 12),
                    _OutcomeCard(mission: mission),
                    const SizedBox(height: 16),
                    MissionOutlineButton(
                      label: '← Retour à l\'historique',
                      onPressed: () => Navigator.of(context).pop(),
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

DateTime? _terminalAt(DriverMissionDetail mission) {
  if (mission.isDelivered) return mission.deliveredAt;
  if (mission.isRejected) return mission.rejectedAt;
  if (mission.hasIncident) return mission.incidentOccurredAt;
  return null;
}

String _terminalLabel(DriverMissionDetail mission) {
  if (mission.isDelivered) return 'Terminée à';
  if (mission.isRejected) return 'Refusée à';
  if (mission.hasIncident) return 'Incident à';
  return 'Terminée à';
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
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
                      style: const TextStyle(
                        color: MissionPalette.navy,
                        fontSize: 20,
                        height: 1.1,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -.3,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'Commande ${mission.orderNumber ?? '—'}',
                      style: const TextStyle(
                        color: MissionPalette.slate,
                        fontSize: 13.8,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              MissionStatusPill.forMission(mission),
            ],
          ),
          const SizedBox(height: 16),
          _InfoGrid(
            left: [
              _InfoItem(Icons.calendar_today_rounded, 'Date', missionDate(mission.pickupScheduledAt)),
              _InfoItem(Icons.schedule_rounded, 'Heure prévue', missionTime(mission.pickupScheduledAt)),
              _InfoItem(Icons.location_on_rounded, 'Destination', mission.displayDestination),
              _InfoItem(Icons.inventory_2_outlined, 'Points de collecte', '${mission.pickupCount}'),
            ],
            right: [
              _InfoItem(Icons.local_shipping_rounded, 'Véhicule', mission.vehicleLabel ?? '—'),
              _InfoItem(Icons.scale_outlined, 'Poids', missionWeight(mission.totalWeightKg)),
              _InfoItem(Icons.assignment_outlined, 'Statut', mission.statusLabel),
              _InfoItem(
                Icons.schedule_rounded,
                _terminalLabel(mission),
                missionTime(_terminalAt(mission)),
                valueColor: mission.isDelivered
                    ? OvanieColors.green
                    : mission.isRejected
                        ? MissionPalette.danger
                        : MissionPalette.amber,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.list_alt_rounded, color: OvanieColors.green, size: 22),
              SizedBox(width: 9),
              Text(
                'Résumé de la mission',
                style: TextStyle(
                  color: MissionPalette.navy,
                  fontSize: 16.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _InfoGrid(
            left: [
              _InfoItem(Icons.calendar_today_rounded, 'Date', missionDate(mission.pickupScheduledAt)),
              _InfoItem(Icons.schedule_rounded, 'Heure prévue', missionTime(mission.pickupScheduledAt)),
              _InfoItem(Icons.location_on_rounded, 'Destination', mission.displayDestination),
              _InfoItem(Icons.inventory_2_outlined, 'Points de collecte', '${mission.pickupCount}'),
            ],
            right: [
              _InfoItem(Icons.local_shipping_rounded, 'Véhicule', mission.vehicleLabel ?? '—'),
              _InfoItem(Icons.scale_outlined, 'Poids', missionWeight(mission.totalWeightKg)),
              _InfoItem(
                Icons.schedule_rounded,
                'Heure de fin',
                missionTime(_terminalAt(mission)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoGrid extends StatelessWidget {
  const _InfoGrid({required this.left, required this.right});

  final List<_InfoItem> left;
  final List<_InfoItem> right;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(children: left),
        ),
        Container(
          width: 1,
          height: left.length * 40.0,
          margin: const EdgeInsets.symmetric(horizontal: 10),
          color: const Color(0xFFDDE4EC),
        ),
        Expanded(
          child: Column(children: right),
        ),
      ],
    );
  }
}

class _InfoItem extends StatelessWidget {
  const _InfoItem(this.icon, this.label, this.value, {this.valueColor});

  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 17, color: MissionPalette.slate),
          const SizedBox(width: 7),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: MissionPalette.slate,
                    fontSize: 11.2,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: valueColor ?? MissionPalette.navy,
                    fontSize: 13,
                    height: 1.2,
                    fontWeight: FontWeight.w800,
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

class _StepEntry {
  const _StepEntry(this.label, this.time, {this.danger = false, this.warning = false});

  final String label;
  final DateTime? time;
  final bool danger;
  final bool warning;
}

class _StepsCard extends StatelessWidget {
  const _StepsCard({required this.mission});

  final DriverMissionDetail mission;

  List<_StepEntry> _steps() {
    final steps = <_StepEntry>[];
    if (mission.acceptedAt != null) {
      steps.add(_StepEntry('Mission acceptée', mission.acceptedAt));
    }
    for (final stop in mission.pickupStops) {
      if (stop.completed && stop.completedAt != null) {
        steps.add(_StepEntry('Collecte point ${stop.index} terminée', stop.completedAt));
      }
    }
    if (mission.isDelivered) {
      steps.add(_StepEntry('Livraison effectuée', mission.deliveredAt));
    } else if (mission.isRejected) {
      steps.add(_StepEntry('Mission refusée', mission.rejectedAt, danger: true));
    } else if (mission.hasIncident) {
      steps.add(_StepEntry('Incident signalé', mission.incidentOccurredAt, warning: true));
    }
    return steps;
  }

  @override
  Widget build(BuildContext context) {
    final steps = _steps();
    if (steps.isEmpty) return const SizedBox.shrink();

    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.checklist_rounded, color: OvanieColors.green, size: 22),
              SizedBox(width: 9),
              Text(
                'Étapes réalisées',
                style: TextStyle(
                  color: MissionPalette.navy,
                  fontSize: 16.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          for (var i = 0; i < steps.length; i++)
            _StepRow(step: steps[i], isLast: i == steps.length - 1),
        ],
      ),
    );
  }
}

class _StepRow extends StatelessWidget {
  const _StepRow({required this.step, required this.isLast});

  final _StepEntry step;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final color = step.danger
        ? MissionPalette.danger
        : step.warning
            ? MissionPalette.amber
            : OvanieColors.green;
    final icon = step.danger
        ? Icons.close_rounded
        : step.warning
            ? Icons.warning_amber_rounded
            : Icons.check_rounded;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                child: Icon(icon, color: Colors.white, size: 15),
              ),
              if (!isLast)
                Expanded(
                  child: Container(width: 2, color: const Color(0xFFE0E5EC)),
                ),
            ],
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 16, top: 3),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      step.label,
                      style: const TextStyle(
                        color: MissionPalette.navy,
                        fontSize: 13.6,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Text(
                    missionTime(step.time),
                    style: const TextStyle(
                      color: MissionPalette.slate,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
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

class _PickupStopsCard extends StatelessWidget {
  const _PickupStopsCard({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.inventory_2_rounded, color: OvanieColors.green, size: 22),
              SizedBox(width: 9),
              Text(
                'Points de collecte',
                style: TextStyle(
                  color: MissionPalette.navy,
                  fontSize: 16.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final stop in mission.pickupStops)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 7),
              child: Row(
                children: [
                  Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      color: Color(0xFFE3F5EC),
                      shape: BoxShape.circle,
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '${stop.index}',
                      style: const TextStyle(
                        color: OvanieColors.green,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      stop.locationLabel.trim().isNotEmpty ? stop.locationLabel : stop.label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: MissionPalette.navy,
                        fontSize: 13.6,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  if (stop.completed)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: const Color(0xFFDFF7EC),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.check_circle_rounded, size: 15, color: OvanieColors.green),
                          SizedBox(width: 4),
                          Text(
                            'Terminé',
                            style: TextStyle(
                              color: OvanieColors.green,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
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

class _RouteCard extends StatelessWidget {
  const _RouteCard({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.map_rounded, color: OvanieColors.green, size: 22),
              SizedBox(width: 9),
              Text(
                'Trajet effectué',
                style: TextStyle(
                  color: MissionPalette.navy,
                  fontSize: 16.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          MissionRouteMap(route: mission.routePlan, points: mission.mapPoints, height: 180),
        ],
      ),
    );
  }
}

class _OutcomeCard extends StatelessWidget {
  const _OutcomeCard({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    if (mission.isDelivered) {
      return MissionSurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Row(
              children: [
                Icon(Icons.location_on_rounded, color: OvanieColors.green, size: 22),
                SizedBox(width: 9),
                Text(
                  'Livraison client',
                  style: TextStyle(
                    color: MissionPalette.navy,
                    fontSize: 16.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFE8F8F1),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 32,
                    height: 32,
                    decoration: const BoxDecoration(color: OvanieColors.green, shape: BoxShape.circle),
                    child: const Icon(Icons.check_rounded, color: Colors.white, size: 20),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Mission bien livrée',
                          style: TextStyle(
                            color: OvanieColors.green,
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        const Text(
                          'Le colis a été livré avec succès au client.',
                          style: TextStyle(
                            color: MissionPalette.slate,
                            fontSize: 12.5,
                            height: 1.4,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Adresse de livraison',
                          style: const TextStyle(
                            color: MissionPalette.slate,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        Text(
                          mission.destinationAddress ?? mission.displayDestination,
                          style: const TextStyle(
                            color: MissionPalette.navy,
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    if (mission.hasIncident) {
      final description = (mission.incidentDescription ?? '').trim();
      return MissionSurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Row(
              children: [
                Icon(Icons.warning_amber_rounded, color: MissionPalette.amber, size: 22),
                SizedBox(width: 9),
                Text(
                  'Incident',
                  style: TextStyle(
                    color: MissionPalette.navy,
                    fontSize: 16.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            MissionTintMessage(
              warning: true,
              icon: Icons.warning_amber_rounded,
              text: '${incidentTypeLabel(mission.incidentType)}'
                  '${description.isNotEmpty ? '\n$description' : ''}',
            ),
          ],
        ),
      );
    }

    if (mission.isRejected) {
      final reason = (mission.rejectionReason ?? '').trim();
      return MissionSurfaceCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Row(
              children: [
                Icon(Icons.cancel_rounded, color: MissionPalette.danger, size: 22),
                SizedBox(width: 9),
                Text(
                  'Mission refusée',
                  style: TextStyle(
                    color: MissionPalette.navy,
                    fontSize: 16.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Container(
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
                    child: const Icon(Icons.close_rounded, color: Colors.white, size: 18),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Mission refusée\n'
                      'Motif : ${reason.isEmpty ? 'Non renseigné' : reason}',
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
            ),
          ],
        ),
      );
    }

    return const SizedBox.shrink();
  }
}
