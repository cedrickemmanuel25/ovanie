import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../models/driver_mission.dart';

class MissionPalette {
  MissionPalette._();

  static const navy = Color(0xFF0A1033);
  static const slate = Color(0xFF66728C);
  static const mint = Color(0xFFF1FBF7);
  static const mintStrong = Color(0xFFE5F8F0);
  static const cardBorder = Color(0xFFDDEBE6);
  static const amber = Color(0xFFD07800);
  static const amberSoft = Color(0xFFFFF1D9);
  static const danger = Color(0xFFFF2A2A);
  static const dangerSoft = Color(0xFFFFE9E9);
  static const softGrey = Color(0xFFE9EDF3);
  static const routeGrey = Color(0xFFAAB4C5);
}

String missionTime(DateTime? value) {
  if (value == null) return '—';
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}

const _missionMonths = [
  'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
  'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.',
];

String missionDate(DateTime? value) {
  if (value == null) return '—';
  return '${value.day} ${_missionMonths[value.month - 1]} ${value.year}';
}

String missionWeight(double value) {
  if (value <= 0) return '—';
  final rounded = value.roundToDouble();
  final text = (value - rounded).abs() < 0.01
      ? rounded.toInt().toString()
      : value.toStringAsFixed(1).replaceAll('.', ',');
  return '${_groupThousands(text)} kg';
}

String missionVolume(double value) {
  if (value <= 0) return '—';
  final fixed = value.toStringAsFixed(value < 10 ? 2 : 1);
  final trimmed = fixed.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
  return '${trimmed.replaceAll('.', ',')} m³';
}

String missionDistance(double? value) {
  if (value == null || value <= 0) return '—';
  final decimals = value < 10 ? 1 : 1;
  return '${value.toStringAsFixed(decimals).replaceAll('.', ',')} km';
}

String missionMoney(double value) {
  if (value <= 0) return '—';
  return '${_groupThousands(value.round().toString())} FCFA';
}

String incidentTypeLabel(String? type) {
  return switch (type) {
    'traffic_jam' => 'Embouteillage',
    'client_absent' => 'Client absent à l\'arrivée',
    'address_issue' => 'Adresse introuvable',
    'vehicle_breakdown' => 'Panne du véhicule',
    'accident' => 'Accident',
    'product_damaged' => 'Colis endommagé',
    'access_impossible' => 'Accès impossible',
    _ => 'Incident signalé',
  };
}

String _groupThousands(String text) {
  final parts = text.split(',');
  final digits = parts.first;
  final buffer = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    final remaining = digits.length - i;
    buffer.write(digits[i]);
    if (remaining > 1 && remaining % 3 == 1) buffer.write(' ');
  }
  if (parts.length > 1) buffer.write(',${parts[1]}');
  return buffer.toString();
}

class MissionPageHeader extends StatelessWidget {
  const MissionPageHeader({
    super.key,
    required this.title,
    required this.subtitle,
    this.showBack = false,
    this.onBack,
    this.trailing,
    this.mainPage = false,
  });

  final String title;
  final String subtitle;
  final bool showBack;
  final VoidCallback? onBack;
  final Widget? trailing;
  final bool mainPage;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFF00854A),
      child: SafeArea(
        bottom: false,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 60),
          child: Padding(
            padding: EdgeInsets.fromLTRB(showBack ? 4 : 16, 6, 12, 10),
            child: Row(
              children: [
                if (showBack)
                  IconButton(
                    tooltip: 'Retour',
                    onPressed: onBack ?? () => Navigator.maybePop(context),
                    icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
                  ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: mainPage ? 21 : 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (subtitle.trim().isNotEmpty)
                        Text(
                          subtitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: Color(0xFFD5F2E3), fontSize: 13),
                        ),
                    ],
                  ),
                ),
                if (trailing != null) ...[
                  const SizedBox(width: 8),
                  trailing!,
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class MissionNotificationButton extends StatelessWidget {
  const MissionNotificationButton({super.key, required this.count, this.onTap});

  final int count;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: SizedBox(
        width: 47,
        height: 47,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            const Center(
              child: Icon(Icons.notifications_none_rounded, color: Colors.white, size: 34),
            ),
            if (count > 0)
              Positioned(
                right: -1,
                top: -4,
                child: Container(
                  constraints: const BoxConstraints(minWidth: 23, minHeight: 23),
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  decoration: const BoxDecoration(
                    color: Color(0xFFF13333),
                    borderRadius: BorderRadius.all(Radius.circular(999)),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    count > 99 ? '99+' : '$count',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class MissionStatusPill extends StatelessWidget {
  const MissionStatusPill({
    super.key,
    required this.label,
    required this.icon,
    required this.kind,
  });

  final String label;
  final IconData icon;
  final MissionStatusKind kind;

  factory MissionStatusPill.forMission(DriverMissionSummaryModel mission) {
    if (mission.isOffered) {
      return const MissionStatusPill(
        label: 'Disponible',
        icon: Icons.notifications_active_rounded,
        kind: MissionStatusKind.warning,
      );
    }
    if (mission.isToAccept) {
      return const MissionStatusPill(
        label: 'À réserver',
        icon: Icons.schedule_rounded,
        kind: MissionStatusKind.warning,
      );
    }
    if (mission.isWaitingVendor) {
      return const MissionStatusPill(
        label: 'Réservée',
        icon: Icons.lock_clock_rounded,
        kind: MissionStatusKind.neutral,
      );
    }
    if (mission.isReadyForPickup) {
      return const MissionStatusPill(
        label: 'Prête',
        icon: Icons.check_circle_rounded,
        kind: MissionStatusKind.success,
      );
    }
    if (mission.isLoaded) {
      return MissionStatusPill(
        label: 'Chargement terminé',
        icon: Icons.local_shipping_rounded,
        kind: MissionStatusKind.success,
      );
    }
    if (mission.isDelivered) {
      return MissionStatusPill(
        label: 'Livrée',
        icon: Icons.check_circle_rounded,
        kind: MissionStatusKind.success,
      );
    }
    if (mission.hasIncident) {
      return MissionStatusPill(
        label: 'Incident',
        icon: Icons.warning_amber_rounded,
        kind: MissionStatusKind.danger,
      );
    }
    if (mission.isRejected) {
      return MissionStatusPill(
        label: 'Refusée',
        icon: Icons.cancel_rounded,
        kind: MissionStatusKind.danger,
      );
    }
    return MissionStatusPill(
      label: mission.statusLabel.trim().isEmpty ? 'En cours' : mission.statusLabel,
      icon: Icons.play_circle_fill_rounded,
      kind: MissionStatusKind.success,
    );
  }

  @override
  Widget build(BuildContext context) {
    final color = switch (kind) {
      MissionStatusKind.success => OvanieColors.green,
      MissionStatusKind.warning => MissionPalette.amber,
      MissionStatusKind.danger => MissionPalette.danger,
      MissionStatusKind.neutral => MissionPalette.slate,
    };
    final background = switch (kind) {
      MissionStatusKind.success => const Color(0xFFDFF7EC),
      MissionStatusKind.warning => MissionPalette.amberSoft,
      MissionStatusKind.danger => MissionPalette.dangerSoft,
      MissionStatusKind.neutral => const Color(0xFFEFF2F6),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 7),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: 13.2,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

enum MissionStatusKind { success, warning, danger, neutral }

class MissionSurfaceCard extends StatelessWidget {
  const MissionSurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.margin,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: MissionPalette.cardBorder),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0B3A25),
            blurRadius: 14,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );
  }
}

class MissionInfoRow extends StatelessWidget {
  const MissionInfoRow({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
    this.iconSize = 21,
    this.valueMaxLines = 2,
    this.labelWidth = 82,
  });

  final IconData icon;
  final String label;
  final String value;
  final double iconSize;
  final int valueMaxLines;
  final double labelWidth;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 26,
            child: Icon(icon, size: iconSize, color: MissionPalette.slate),
          ),
          const SizedBox(width: 7),
          SizedBox(
            width: labelWidth,
            child: Text(
              label,
              style: const TextStyle(
                color: MissionPalette.slate,
                fontSize: 12.5,
                height: 1.25,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              value,
              maxLines: valueMaxLines,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: MissionPalette.navy,
                fontSize: 13.2,
                height: 1.25,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class MissionProgress extends StatelessWidget {
  const MissionProgress({
    super.key,
    required this.value,
    this.label,
    this.trailingLabel,
  });

  final int value;
  final String? label;
  final String? trailingLabel;

  @override
  Widget build(BuildContext context) {
    final safe = value.clamp(0, 100);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (label != null || trailingLabel != null) ...[
          Row(
            children: [
              if (label != null)
                Expanded(
                  child: Text(
                    label!,
                    style: const TextStyle(
                      color: MissionPalette.slate,
                      fontSize: 13.2,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              if (trailingLabel != null)
                Text(
                  trailingLabel!,
                  style: const TextStyle(
                    color: MissionPalette.navy,
                    fontSize: 13.2,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
        ],
        Row(
          children: [
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(999),
                child: SizedBox(
                  height: 10,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      const ColoredBox(color: Color(0xFFE0E5EC)),
                      FractionallySizedBox(
                        alignment: Alignment.centerLeft,
                        widthFactor: safe / 100,
                        child: const DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [Color(0xFF00A359), Color(0xFF008E4D)],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Text(
              '$safe %',
              style: const TextStyle(
                color: MissionPalette.navy,
                fontSize: 15.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class MissionTintMessage extends StatelessWidget {
  const MissionTintMessage({
    super.key,
    required this.text,
    this.warning = false,
    this.icon,
  });

  final String text;
  final bool warning;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final color = warning ? MissionPalette.amber : OvanieColors.green;
    final bg = warning ? const Color(0xFFFFF3E1) : const Color(0xFFE8F8F1);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            child: Icon(icon ?? (warning ? Icons.info_rounded : Icons.check_rounded), color: Colors.white, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: color,
                fontSize: 13.2,
                height: 1.45,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class MissionPrimaryButton extends StatelessWidget {
  const MissionPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.enabled = true,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool enabled;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final active = enabled && !loading && onPressed != null;
    return SizedBox(
      height: 51,
      child: FilledButton(
        onPressed: active ? onPressed : null,
        style: FilledButton.styleFrom(
          backgroundColor: OvanieColors.green,
          disabledBackgroundColor: const Color(0xFFB6DED0),
          foregroundColor: Colors.white,
          disabledForegroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
        ),
        child: loading
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[
                    Icon(icon, size: 21),
                    const SizedBox(width: 8),
                  ],
                  Flexible(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(label, maxLines: 1),
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}

class MissionOutlineButton extends StatelessWidget {
  const MissionOutlineButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.danger = false,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool danger;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final color = danger ? MissionPalette.danger : OvanieColors.green;
    return SizedBox(
      height: 51,
      child: OutlinedButton(
        onPressed: loading ? null : onPressed,
        style: OutlinedButton.styleFrom(
          foregroundColor: color,
          side: BorderSide(color: color, width: 1.5),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
        ),
        child: loading
            ? SizedBox(
                width: 21,
                height: 21,
                child: CircularProgressIndicator(strokeWidth: 2.3, color: color),
              )
            : FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(label, maxLines: 1),
              ),
      ),
    );
  }
}

class MissionRouteMap extends StatelessWidget {
  const MissionRouteMap({
    super.key,
    required this.route,
    required this.points,
    this.height = 170,
    this.compact = false,
  });

  final DriverMissionRoutePlan? route;
  final List<DriverMissionGeoPoint> points;
  final double height;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final geometry = route?.geometry ?? const <DriverMissionCoordinate>[];
    final allPoints = route?.points.isNotEmpty == true ? route!.points : points;
    final hasCoordinates = geometry.isNotEmpty || allPoints.isNotEmpty;

    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: SizedBox(
        height: height,
        width: double.infinity,
        child: DecoratedBox(
          decoration: const BoxDecoration(color: Color(0xFFF1F5F5)),
          child: Stack(
            fit: StackFit.expand,
            children: [
              CustomPaint(
                painter: _MissionMapPainter(
                  geometry: geometry,
                  points: allPoints,
                  routeAvailable: route?.success == true,
                ),
              ),
              if (!hasCoordinates || route?.success == false && geometry.isEmpty)
                Align(
                  alignment: Alignment.bottomCenter,
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                    color: Colors.white.withValues(alpha: .88),
                    child: Text(
                      (route?.message ?? 'Itinéraire en attente des positions GPS.'),
                      maxLines: compact ? 1 : 2,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: MissionPalette.slate,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MissionMapPainter extends CustomPainter {
  _MissionMapPainter({
    required this.geometry,
    required this.points,
    required this.routeAvailable,
  });

  final List<DriverMissionCoordinate> geometry;
  final List<DriverMissionGeoPoint> points;
  final bool routeAvailable;

  @override
  void paint(Canvas canvas, Size size) {
    _paintBase(canvas, size);

    final coordinates = <DriverMissionCoordinate>[...geometry];
    if (coordinates.isEmpty) {
      coordinates.addAll(points.map((point) => DriverMissionCoordinate(
            latitude: point.latitude,
            longitude: point.longitude,
          )));
    }
    if (coordinates.isEmpty) return;

    var minLat = coordinates.first.latitude;
    var maxLat = coordinates.first.latitude;
    var minLng = coordinates.first.longitude;
    var maxLng = coordinates.first.longitude;
    for (final point in coordinates) {
      minLat = math.min(minLat, point.latitude);
      maxLat = math.max(maxLat, point.latitude);
      minLng = math.min(minLng, point.longitude);
      maxLng = math.max(maxLng, point.longitude);
    }
    if ((maxLat - minLat).abs() < .00001) {
      minLat -= .005;
      maxLat += .005;
    }
    if ((maxLng - minLng).abs() < .00001) {
      minLng -= .005;
      maxLng += .005;
    }

    const pad = 20.0;
    Offset project(double lat, double lng) {
      final x = pad + ((lng - minLng) / (maxLng - minLng)) * (size.width - pad * 2);
      final y = pad + (1 - ((lat - minLat) / (maxLat - minLat))) * (size.height - pad * 2);
      return Offset(x, y);
    }

    if (routeAvailable && geometry.length >= 2) {
      final path = Path();
      for (var i = 0; i < geometry.length; i++) {
        final p = project(geometry[i].latitude, geometry[i].longitude);
        if (i == 0) {
          path.moveTo(p.dx, p.dy);
        } else {
          path.lineTo(p.dx, p.dy);
        }
      }
      canvas.drawPath(
        path,
        Paint()
          ..color = OvanieColors.green
          ..style = PaintingStyle.stroke
          ..strokeWidth = 5
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round,
      );
    }

    for (final point in points) {
      final p = project(point.latitude, point.longitude);
      final isDestination = point.type == 'destination';
      final isDriver = point.type == 'driver';
      final color = isDestination
          ? const Color(0xFFF23E3E)
          : isDriver
              ? const Color(0xFF17325C)
              : OvanieColors.green;
      canvas.drawCircle(
        p,
        isDestination ? 10 : 8,
        Paint()..color = Colors.white,
      );
      canvas.drawCircle(
        p,
        isDestination ? 7.5 : 5.5,
        Paint()..color = color,
      );
      canvas.drawCircle(
        p,
        isDestination ? 2.2 : 1.8,
        Paint()..color = Colors.white,
      );
    }
  }

  void _paintBase(Canvas canvas, Size size) {
    final road = Paint()
      ..color = Colors.white.withValues(alpha: .94)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3;
    final minor = Paint()
      ..color = const Color(0xFFDDE6E8)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.1;

    for (var i = 1; i < 7; i++) {
      final y = size.height * i / 7;
      final path = Path()
        ..moveTo(0, y)
        ..cubicTo(size.width * .25, y - 12, size.width * .65, y + 15, size.width, y - 4);
      canvas.drawPath(path, i.isEven ? road : minor);
    }
    for (var i = 1; i < 7; i++) {
      final x = size.width * i / 7;
      final path = Path()
        ..moveTo(x, 0)
        ..cubicTo(x + 15, size.height * .28, x - 12, size.height * .7, x + 5, size.height);
      canvas.drawPath(path, i.isOdd ? road : minor);
    }

    final water = Path()
      ..moveTo(0, size.height * .73)
      ..cubicTo(size.width * .22, size.height * .62, size.width * .35, size.height * .9, size.width * .55, size.height * .8)
      ..cubicTo(size.width * .72, size.height * .72, size.width * .82, size.height * .95, size.width, size.height * .82)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(water, Paint()..color = const Color(0xFFCDECF7));
  }

  @override
  bool shouldRepaint(covariant _MissionMapPainter oldDelegate) {
    return oldDelegate.geometry != geometry ||
        oldDelegate.points != points ||
        oldDelegate.routeAvailable != routeAvailable;
  }
}

class MissionSectionTitle extends StatelessWidget {
  const MissionSectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        color: MissionPalette.navy,
        fontSize: 20,
        height: 1.1,
        fontWeight: FontWeight.w900,
        letterSpacing: -.35,
      ),
    );
  }
}

class MissionDestinationBox extends StatelessWidget {
  const MissionDestinationBox({
    super.key,
    required this.destination,
    this.subtitle,
  });

  final String destination;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF9F3),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: [
          const Icon(Icons.location_on_rounded, color: OvanieColors.green, size: 31),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (subtitle != null) ...[
                  Text(
                    subtitle!,
                    style: const TextStyle(
                      color: MissionPalette.slate,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                ],
                Text(
                  destination,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: MissionPalette.navy,
                    fontSize: 15.3,
                    height: 1.2,
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

/// Gain du livreur pour la mission.
class MissionEarningsCard extends StatelessWidget {
  const MissionEarningsCard({super.key, required this.netAmount});

  final double netAmount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF9F3),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFCDEEDF)),
      ),
      child: Row(
        children: [
          const Icon(Icons.payments_rounded, color: OvanieColors.green, size: 22),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Votre gain',
              style: TextStyle(color: MissionPalette.navy, fontSize: 15, fontWeight: FontWeight.w700),
            ),
          ),
          Text(
            missionMoney(netAmount),
            style: const TextStyle(color: OvanieColors.green, fontSize: 20, fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

/// Suivi d'approche : l'arrivée (vendeur ou client) est validée
/// automatiquement par le GPS, sans bouton.
class MissionArrivalRadar extends StatelessWidget {
  const MissionArrivalRadar({
    super.key,
    required this.placeLabel,
    this.distanceMeters,
    this.gpsMissing = false,
  });

  final String placeLabel;
  final double? distanceMeters;
  final bool gpsMissing;

  @override
  Widget build(BuildContext context) {
    final distance = distanceMeters;
    final onSite = distance != null && distance <= 150;
    final String headline;
    if (gpsMissing) {
      headline = 'Activez votre GPS';
    } else if (distance == null) {
      headline = 'Localisation en cours…';
    } else if (onSite) {
      headline = 'Vous êtes sur place — validation en cours';
    } else {
      headline = 'À ${missionDistance(distance / 1000)}';
    }

    return MissionSurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: gpsMissing ? const Color(0xFFFFF3E1) : const Color(0xFFEAF9F3),
              shape: BoxShape.circle,
            ),
            child: Icon(
              gpsMissing ? Icons.location_disabled_rounded : Icons.my_location_rounded,
              color: gpsMissing ? MissionPalette.amber : OvanieColors.green,
              size: 24,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  headline,
                  style: const TextStyle(color: MissionPalette.navy, fontSize: 16, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 3),
                Text(
                  gpsMissing
                      ? 'Sans GPS, votre arrivée $placeLabel ne peut pas être validée.'
                      : 'Votre arrivée $placeLabel est détectée automatiquement par le GPS. Aucune action n’est nécessaire.',
                  style: const TextStyle(color: MissionPalette.slate, fontSize: 12.5, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
