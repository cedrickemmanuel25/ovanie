import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:mapbox_maps_flutter/mapbox_maps_flutter.dart' as mbx;

import '../../../app/theme.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import 'mission_ui.dart';

/// Carte d'aperçu d'une mission, au même rendu que l'écran de suivi :
/// fond Mapbox réel, itinéraire OVANIE en vert, points de collecte numérotés
/// et point client en rouge, cadrés automatiquement.
///
/// La carte est fixe (pas de gestes) pour ne pas gêner le défilement de
/// l'écran. Sans configuration Mapbox, l'aperçu schématique est conservé.
class MissionLiveMap extends StatefulWidget {
  const MissionLiveMap({
    super.key,
    required this.route,
    required this.points,
    this.height = 230,
  });

  final DriverMissionRoutePlan? route;
  final List<DriverMissionGeoPoint> points;
  final double height;

  @override
  State<MissionLiveMap> createState() => _MissionLiveMapState();
}

class _MissionLiveMapState extends State<MissionLiveMap> {
  static Future<DriverMapConfig?>? _configFuture;
  static final Map<String, Uint8List> _markerCache = <String, Uint8List>{};

  mbx.MapboxMap? _map;
  mbx.PointAnnotationManager? _pointManager;
  mbx.PolylineAnnotationManager? _lineManager;
  bool _styleLoaded = false;
  String? _styleUri;
  bool _unavailable = false;

  List<DriverMissionGeoPoint> get _points {
    final routePoints = widget.route?.points ?? const <DriverMissionGeoPoint>[];
    final source = routePoints.isNotEmpty ? routePoints : widget.points;
    return source.where((point) => point.type != 'driver').toList(growable: false);
  }

  @override
  void initState() {
    super.initState();
    _configFuture ??= _loadConfig();
    _configFuture!.then((config) {
      if (!mounted) return;
      if (config == null) {
        setState(() => _unavailable = true);
        return;
      }
      setState(() {
        _styleUri = config.styleUri.trim().isEmpty ? mbx.MapboxStyles.MAPBOX_STREETS : config.styleUri.trim();
      });
    });
  }

  static Future<DriverMapConfig?> _loadConfig() async {
    try {
      final config = await MissionRepository.instance.fetchMapConfig();
      final token = config.accessToken.trim();
      if (token.isEmpty || !token.startsWith('pk.')) return null;
      mbx.MapboxOptions.setAccessToken(token);
      return config;
    } catch (_) {
      // Réessayer au prochain affichage (réseau momentanément absent).
      _configFuture = null;
      return null;
    }
  }

  @override
  void didUpdateWidget(covariant MissionLiveMap oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.route != widget.route || oldWidget.points != widget.points) {
      _render();
    }
  }

  Future<void> _onMapCreated(mbx.MapboxMap map) async {
    _map = map;
    await Future.wait([
      map.gestures.updateSettings(mbx.GesturesSettings(
        scrollEnabled: false,
        rotateEnabled: false,
        pitchEnabled: false,
        pinchToZoomEnabled: false,
        doubleTapToZoomInEnabled: false,
        doubleTouchToZoomOutEnabled: false,
        quickZoomEnabled: false,
      )),
      map.scaleBar.updateSettings(mbx.ScaleBarSettings(enabled: false)),
      map.compass.updateSettings(mbx.CompassSettings(enabled: false)),
      map.logo.updateSettings(mbx.LogoSettings(position: mbx.OrnamentPosition.BOTTOM_LEFT, marginBottom: 6, marginLeft: 6)),
      map.attribution.updateSettings(mbx.AttributionSettings(position: mbx.OrnamentPosition.BOTTOM_RIGHT, marginBottom: 4, marginRight: 4)),
    ]);
  }

  Future<void> _onStyleLoaded(mbx.StyleLoadedEventData _) async {
    final map = _map;
    if (map == null) return;
    _styleLoaded = true;
    _lineManager ??= await map.annotations.createPolylineAnnotationManager();
    _pointManager ??= await map.annotations.createPointAnnotationManager();
    await _pointManager?.setIconAllowOverlap(true);
    await _pointManager?.setTextAllowOverlap(false);
    await map.location.updateSettings(mbx.LocationComponentSettings(enabled: false));
    await _render();
  }

  Future<void> _render() async {
    final map = _map;
    final lines = _lineManager;
    final pins = _pointManager;
    if (!_styleLoaded || map == null || lines == null || pins == null) return;

    await lines.deleteAll();
    await pins.deleteAll();

    final geometry = widget.route?.geometry ?? const <DriverMissionCoordinate>[];
    if (geometry.length >= 2) {
      await lines.create(mbx.PolylineAnnotationOptions(
        geometry: mbx.LineString(
          coordinates: geometry.map((p) => mbx.Position(p.longitude, p.latitude)).toList(growable: false),
        ),
        lineColor: OvanieColors.green.toARGB32(),
        lineWidth: 5.5,
        lineBorderColor: Colors.white.toARGB32(),
        lineBorderWidth: 2.0,
        lineJoin: mbx.LineJoin.ROUND,
      ));
    }

    var pickupIndex = 0;
    for (final point in _points) {
      final isDestination = point.type == 'destination';
      if (!isDestination) pickupIndex++;
      final color = isDestination
          ? const Color(0xFFF23E3E)
          : point.completed
              ? OvanieColors.green
              : const Color(0xFF17325C);
      final image = await _marker(
        color: color,
        label: isDestination ? null : '$pickupIndex',
        icon: isDestination ? Icons.flag_rounded : null,
      );
      await pins.create(mbx.PointAnnotationOptions(
        geometry: mbx.Point(coordinates: mbx.Position(point.longitude, point.latitude)),
        image: image,
        iconSize: .62,
        iconAnchor: mbx.IconAnchor.CENTER,
        textField: isDestination ? 'Client' : 'Collecte $pickupIndex',
        textSize: 11.5,
        textColor: const Color(0xFF13203D).toARGB32(),
        textHaloColor: Colors.white.toARGB32(),
        textHaloWidth: 2.0,
        textAnchor: mbx.TextAnchor.TOP,
        textOffset: const <double?>[0, 1.5],
      ));
    }

    await _frame(geometry);
  }

  Future<void> _frame(List<DriverMissionCoordinate> geometry) async {
    final map = _map;
    if (map == null) return;
    final coordinates = <mbx.Point>[
      ...geometry.map((p) => mbx.Point(coordinates: mbx.Position(p.longitude, p.latitude))),
      ..._points.map((p) => mbx.Point(coordinates: mbx.Position(p.longitude, p.latitude))),
    ];
    if (coordinates.isEmpty) return;
    if (coordinates.length == 1) {
      await map.setCamera(mbx.CameraOptions(center: coordinates.first, zoom: 14.5));
      return;
    }
    try {
      final camera = await map.cameraForCoordinates(
        coordinates,
        mbx.MbxEdgeInsets(top: 42, left: 36, bottom: 48, right: 36),
        null,
        0,
      );
      await map.setCamera(camera);
    } catch (_) {
      await map.setCamera(mbx.CameraOptions(center: coordinates.first, zoom: 12.5));
    }
  }

  Future<Uint8List> _marker({required Color color, String? label, IconData? icon}) async {
    final key = '${color.toARGB32()}-$label-${icon?.codePoint}';
    final cached = _markerCache[key];
    if (cached != null) return cached;

    const size = 96.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    const center = Offset(size / 2, size / 2);
    canvas.drawCircle(
      center,
      40,
      Paint()
        ..color = Colors.black.withValues(alpha: .16)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6),
    );
    canvas.drawCircle(center, 37, Paint()..color = Colors.white);
    canvas.drawCircle(center, 31, Paint()..color = color);

    final painter = TextPainter(
      textDirection: TextDirection.ltr,
      text: TextSpan(
        text: icon != null ? String.fromCharCode(icon.codePoint) : (label ?? ''),
        style: TextStyle(
          fontSize: icon != null ? 36 : 32,
          fontWeight: FontWeight.w900,
          fontFamily: icon?.fontFamily,
          package: icon?.fontPackage,
          color: Colors.white,
        ),
      ),
    )..layout();
    painter.paint(canvas, Offset(center.dx - painter.width / 2, center.dy - painter.height / 2));

    final image = await recorder.endRecording().toImage(size.toInt(), size.toInt());
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    final bytes = data!.buffer.asUint8List();
    _markerCache[key] = bytes;
    return bytes;
  }

  @override
  Widget build(BuildContext context) {
    final route = widget.route;
    final distance = route?.distanceKm;
    final duration = route?.durationMinutes;
    final hasSummary = (distance ?? 0) > 0 || (duration ?? 0) > 0;

    Widget map;
    if (_unavailable || _points.isEmpty) {
      map = MissionRouteMap(route: route, points: widget.points, height: widget.height);
    } else if (_styleUri == null) {
      map = SizedBox(
        height: widget.height,
        child: const ColoredBox(
          color: Color(0xFFF1F5F5),
          child: Center(
            child: SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(strokeWidth: 2.4, color: OvanieColors.green),
            ),
          ),
        ),
      );
    } else {
      map = SizedBox(
        height: widget.height,
        child: mbx.MapWidget(
          key: ValueKey('mission-map-${_points.map((p) => p.id).join('-')}'),
          styleUri: _styleUri!,
          onMapCreated: _onMapCreated,
          onStyleLoadedListener: _onStyleLoaded,
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Stack(
        children: [
          map,
          if (hasSummary)
            Positioned(
              left: 10,
              top: 10,
              child: _RouteBadge(distanceKm: distance, durationMinutes: duration),
            ),
        ],
      ),
    );
  }
}

class _RouteBadge extends StatelessWidget {
  const _RouteBadge({this.distanceKm, this.durationMinutes});

  final double? distanceKm;
  final int? durationMinutes;

  @override
  Widget build(BuildContext context) {
    final parts = <String>[
      if ((distanceKm ?? 0) > 0) missionDistance(distanceKm),
      if ((durationMinutes ?? 0) > 0) '${durationMinutes!} min',
    ];
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        boxShadow: const [BoxShadow(color: Color(0x22000000), blurRadius: 8, offset: Offset(0, 2))],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.route_rounded, size: 16, color: OvanieColors.green),
          const SizedBox(width: 6),
          Text(
            parts.join(' • '),
            style: const TextStyle(color: MissionPalette.navy, fontSize: 12, fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}
