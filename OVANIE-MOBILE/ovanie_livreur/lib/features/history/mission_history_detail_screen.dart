import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../missions/models/driver_mission.dart';
import '../missions/widgets/mission_live_map.dart';
import '../missions/widgets/mission_ui.dart';

/// Sections d'une mission terminée (livrée, incident ou refusée), affichées
/// dans l'écran unique [MissionDetailScreen].
class MissionArchiveSections extends StatelessWidget {
  const MissionArchiveSections({super.key, required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _HeaderCard(mission: mission),
        if (mission.netAmount > 0) ...[
          const SizedBox(height: 12),
          MissionEarningsCard(netAmount: mission.netAmount),
        ],
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
      ],
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
    final rows = <(IconData, String, String)>[
      (Icons.receipt_long_outlined, 'Commande', mission.orderNumber ?? ''),
      (Icons.location_on_outlined, 'Destination', mission.displayDestination),
      (Icons.schedule_rounded, _terminalLabel(mission), _terminalAt(mission) == null ? '' : '${missionDate(_terminalAt(mission))} · ${missionTime(_terminalAt(mission))}'),
      (Icons.inventory_2_outlined, 'Collectes', '${mission.pickupCount}'),
      (Icons.local_shipping_outlined, 'Véhicule', mission.vehicleLabel ?? ''),
      (Icons.scale_outlined, 'Poids', missionWeight(mission.totalWeightKg)),
    ].where((row) => row.$3.trim().isNotEmpty && row.$3.trim() != '—').toList();
    return MissionSurfaceCard(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 11),
              decoration: BoxDecoration(
                border: i == rows.length - 1 ? null : const Border(bottom: BorderSide(color: Color(0xFFE6ECE8))),
              ),
              child: Row(
                children: [
                  Icon(rows[i].$1, size: 19, color: MissionPalette.slate),
                  const SizedBox(width: 10),
                  Text(rows[i].$2, style: const TextStyle(color: MissionPalette.slate, fontSize: 14)),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      rows[i].$3,
                      textAlign: TextAlign.right,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: MissionPalette.navy, fontSize: 14, fontWeight: FontWeight.w600),
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
          MissionLiveMap(route: mission.routePlan, points: mission.mapPoints, height: 200),
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
