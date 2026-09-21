import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../models/driver_mission.dart';
import 'mission_ui.dart';

class MissionOverviewBlock extends StatelessWidget {
  const MissionOverviewBlock({
    super.key,
    required this.mission,
    this.progressTitle = 'Préparation de la commande',
    this.progressValue,
    this.progressTrailingLabel,
    this.message,
    this.messageWarning = false,
    this.progressCompletedText,
  });

  final DriverMissionDetail mission;
  final String progressTitle;
  final int? progressValue;
  final String? progressTrailingLabel;
  final String? message;
  final bool messageWarning;
  final String? progressCompletedText;

  @override
  Widget build(BuildContext context) {
    final progress = progressValue ?? mission.preparationPercent;
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Mission ${mission.missionNumber}',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: MissionPalette.navy,
              fontSize: 20,
              height: 1.12,
              fontWeight: FontWeight.w900,
              letterSpacing: -.4,
            ),
          ),
          if ((mission.orderNumber ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 3),
            Text(
              'Commande ${mission.orderNumber}',
              style: const TextStyle(
                color: MissionPalette.slate,
                fontSize: 15,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
          const SizedBox(height: 17),
          _MetricsColumns(mission: mission),
          const SizedBox(height: 16),
          Text(
            progressTitle,
            style: const TextStyle(
              color: MissionPalette.slate,
              fontSize: 13.5,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          MissionProgress(
            value: progress,
            trailingLabel: progressTrailingLabel,
          ),
          if (progressCompletedText != null) ...[
            const SizedBox(height: 12),
            MissionTintMessage(text: progressCompletedText!),
          ],
          if (message != null) ...[
            const SizedBox(height: 12),
            MissionTintMessage(text: message!, warning: messageWarning),
          ],
        ],
      ),
    );
  }
}

class _MetricsColumns extends StatelessWidget {
  const _MetricsColumns({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final left = Column(
          children: [
            MissionInfoRow(
              icon: Icons.schedule_rounded,
              label: 'Heure prévue',
              value: missionTime(mission.pickupScheduledAt),
            ),
            MissionInfoRow(
              icon: Icons.location_on_rounded,
              label: 'Destination',
              value: mission.displayDestination,
            ),
            MissionInfoRow(
              icon: Icons.inventory_2_outlined,
              label: 'Points de collecte',
              value: '${mission.pickupCount} point${mission.pickupCount > 1 ? 's' : ''}',
            ),
            MissionInfoRow(
              icon: Icons.receipt_long_outlined,
              label: 'Articles',
              value: '${mission.itemCount} article${mission.itemCount > 1 ? 's' : ''}',
            ),
          ],
        );
        final right = Column(
          children: [
            MissionInfoRow(
              icon: Icons.scale_outlined,
              label: 'Poids total',
              labelWidth: 62,
              value: missionWeight(mission.totalWeightKg),
            ),
            MissionInfoRow(
              icon: Icons.view_in_ar_outlined,
              label: 'Volume',
              labelWidth: 62,
              value: missionVolume(mission.totalVolumeM3),
            ),
            MissionInfoRow(
              icon: Icons.local_shipping_rounded,
              label: 'Véhicule prévu',
              labelWidth: 62,
              value: (mission.vehicleLabel ?? 'Véhicule').trim(),
            ),
          ],
        );

        if (constraints.maxWidth < 570) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(flex: 6, child: left),
              Container(
                width: 1,
                height: 118,
                margin: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                color: const Color(0xFFDCE3EB),
              ),
              Expanded(flex: 5, child: right),
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: left),
            Container(
              width: 1,
              height: 118,
              margin: const EdgeInsets.symmetric(horizontal: 18, vertical: 5),
              color: const Color(0xFFDCE3EB),
            ),
            Expanded(child: right),
          ],
        );
      },
    );
  }
}

class MissionPreparationStops extends StatelessWidget {
  const MissionPreparationStops({
    super.key,
    required this.stops,
    this.title = 'Points de collecte',
  });

  final List<DriverMissionPickupStop> stops;
  final String title;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          MissionSectionTitle(title),
          const SizedBox(height: 13),
          for (var i = 0; i < stops.length; i++)
            _PreparationStopRow(
              stop: stops[i],
              first: i == 0,
              last: i == stops.length - 1,
            ),
        ],
      ),
    );
  }
}

class _PreparationStopRow extends StatelessWidget {
  const _PreparationStopRow({
    required this.stop,
    required this.first,
    required this.last,
  });

  final DriverMissionPickupStop stop;
  final bool first;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 52,
            child: Column(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: stop.ready ? OvanieColors.green : const Color(0xFF708098),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '${stop.index}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 14.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (!last)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 5),
                      color: stop.ready ? const Color(0xFF8AD7B7) : const Color(0xFFE1E7EE),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 5),
          Expanded(
            child: Container(
              padding: EdgeInsets.only(bottom: last ? 0 : 14),
              margin: EdgeInsets.only(bottom: last ? 0 : 10),
              decoration: BoxDecoration(
                border: last
                    ? null
                    : const Border(
                        bottom: BorderSide(color: Color(0xFFE1E7EE)),
                      ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          stop.label,
                          style: const TextStyle(
                            color: MissionPalette.navy,
                            fontSize: 15.2,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          stop.locationLabel,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: MissionPalette.navy,
                            fontSize: 13.3,
                            height: 1.25,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${stop.itemCount} article${stop.itemCount > 1 ? 's' : ''} • ${missionWeight(stop.weightKg)}',
                          style: const TextStyle(
                            color: MissionPalette.slate,
                            fontSize: 12.4,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  _StopPreparationPill(ready: stop.ready),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StopPreparationPill extends StatelessWidget {
  const _StopPreparationPill({required this.ready});

  final bool ready;

  @override
  Widget build(BuildContext context) {
    final color = ready ? OvanieColors.green : MissionPalette.amber;
    final bg = ready ? const Color(0xFFDFF7EC) : MissionPalette.amberSoft;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(16)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(ready ? Icons.check_circle_rounded : Icons.schedule_rounded, color: color, size: 19),
          const SizedBox(width: 6),
          Text(
            ready ? 'Prêt' : 'En préparation',
            style: TextStyle(color: color, fontSize: 12.3, fontWeight: FontWeight.w900),
          ),
        ],
      ),
    );
  }
}

class MissionCollectingStops extends StatelessWidget {
  const MissionCollectingStops({
    super.key,
    required this.stops,
    this.onNavigate,
  });

  final List<DriverMissionPickupStop> stops;
  final ValueChanged<DriverMissionPickupStop>? onNavigate;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const MissionSectionTitle('Points de collecte'),
          const SizedBox(height: 13),
          for (var i = 0; i < stops.length; i++)
            _CollectingStopRow(
              stop: stops[i],
              last: i == stops.length - 1,
              onNavigate: onNavigate,
            ),
        ],
      ),
    );
  }
}

class _CollectingStopRow extends StatelessWidget {
  const _CollectingStopRow({
    required this.stop,
    required this.last,
    required this.onNavigate,
  });

  final DriverMissionPickupStop stop;
  final bool last;
  final ValueChanged<DriverMissionPickupStop>? onNavigate;

  @override
  Widget build(BuildContext context) {
    final circleColor = stop.completed || stop.current
        ? OvanieColors.green
        : const Color(0xFF7A879D);
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 52,
            child: Column(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: circleColor,
                    shape: BoxShape.circle,
                    border: stop.current
                        ? Border.all(color: const Color(0xFFA9E5CD), width: 4)
                        : null,
                  ),
                  child: Text(
                    '${stop.index}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 14.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (!last)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 5),
                      color: stop.completed ? const Color(0xFF77D2AC) : const Color(0xFFE0E6ED),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 7),
          Expanded(
            child: Container(
              padding: EdgeInsets.only(bottom: last ? 0 : 13),
              margin: EdgeInsets.only(bottom: last ? 0 : 9),
              decoration: BoxDecoration(
                border: last
                    ? null
                    : const Border(bottom: BorderSide(color: Color(0xFFE1E7EE))),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          stop.label,
                          style: const TextStyle(
                            color: MissionPalette.navy,
                            fontSize: 15.2,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 1),
                        Text(
                          stop.locationLabel,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: MissionPalette.navy,
                            fontSize: 13.3,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${stop.itemCount} article${stop.itemCount > 1 ? 's' : ''} • ${missionWeight(stop.weightKg)}',
                          style: const TextStyle(
                            color: MissionPalette.slate,
                            fontSize: 12.2,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        if (stop.itemsInline.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF1F3F6),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.inventory_2_outlined, size: 16, color: MissionPalette.slate),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    stop.itemsInline,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: MissionPalette.slate,
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  SizedBox(
                    width: 148,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        _CollectingStatusPill(stop: stop),
                        if (stop.completedAt != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            missionTime(stop.completedAt),
                            style: const TextStyle(
                              color: MissionPalette.slate,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                        if (stop.current && onNavigate != null) ...[
                          const SizedBox(height: 8),
                          SizedBox(
                            height: 38,
                            width: 148,
                            child: FilledButton.icon(
                              onPressed: () => onNavigate!(stop),
                              icon: const Icon(Icons.navigation_rounded, size: 18),
                              label: const Text('Naviguer vers ce point'),
                              style: FilledButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 8),
                                backgroundColor: OvanieColors.green,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                                textStyle: const TextStyle(fontSize: 10.8, fontWeight: FontWeight.w900),
                              ),
                            ),
                          ),
                        ],
                      ],
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

class _CollectingStatusPill extends StatelessWidget {
  const _CollectingStatusPill({required this.stop});

  final DriverMissionPickupStop stop;

  @override
  Widget build(BuildContext context) {
    final label = stop.completed
        ? 'Collecte terminée'
        : stop.current
            ? 'Point en cours'
            : 'À venir';
    final color = stop.completed || stop.current ? OvanieColors.green : MissionPalette.slate;
    final bg = stop.completed || stop.current ? const Color(0xFFDFF7EC) : const Color(0xFFEEF1F5);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(15)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            stop.completed ? Icons.check_circle_rounded : Icons.play_circle_fill_rounded,
            color: color,
            size: 18,
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: color, fontSize: 11.3, fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }
}

class MissionItineraryStrip extends StatelessWidget {
  const MissionItineraryStrip({super.key, required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    final nodes = <_RouteNode>[
      ...mission.pickupStops.map((stop) => _RouteNode(
            title: stop.label,
            subtitle: stop.locationLabel,
            done: stop.completed,
            active: stop.current,
            destination: false,
          )),
      _RouteNode(
        title: 'Livraison',
        subtitle: mission.displayDestination,
        done: mission.isDelivered,
        active: mission.isLoaded || mission.isInTransit || mission.isArrived,
        destination: true,
      ),
    ];

    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Itinéraire de la mission',
            style: TextStyle(
              color: MissionPalette.navy,
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 78,
            child: LayoutBuilder(
              builder: (context, constraints) {
                final width = nodes.isEmpty ? constraints.maxWidth : constraints.maxWidth / nodes.length;
                return Stack(
                  children: [
                    Positioned(
                      top: 15,
                      left: width / 2,
                      right: width / 2,
                      child: Container(height: 3, color: const Color(0xFFD5DCE6)),
                    ),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        for (var i = 0; i < nodes.length; i++)
                          SizedBox(
                            width: width,
                            child: _RouteNodeWidget(node: nodes[i]),
                          ),
                      ],
                    ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _RouteNode {
  const _RouteNode({
    required this.title,
    required this.subtitle,
    required this.done,
    required this.active,
    required this.destination,
  });

  final String title;
  final String subtitle;
  final bool done;
  final bool active;
  final bool destination;
}

class _RouteNodeWidget extends StatelessWidget {
  const _RouteNodeWidget({required this.node});

  final _RouteNode node;

  @override
  Widget build(BuildContext context) {
    final color = node.done || node.active ? OvanieColors.green : const Color(0xFF7B8799);
    return Column(
      children: [
        Container(
          width: 31,
          height: 31,
          decoration: BoxDecoration(
            color: node.active ? const Color(0xFFDDF7EC) : Colors.white,
            shape: BoxShape.circle,
          ),
          alignment: Alignment.center,
          child: Icon(
            node.destination
                ? Icons.location_on_rounded
                : node.done
                    ? Icons.check_circle_rounded
                    : Icons.circle,
            size: node.destination ? 25 : 23,
            color: color,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          node.title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: MissionPalette.navy,
            fontSize: 10.8,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          node.subtitle,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: MissionPalette.slate,
            fontSize: 9.5,
            height: 1.1,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}
