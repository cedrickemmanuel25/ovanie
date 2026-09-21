import 'dart:async';
import 'dart:math' as math;
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mapbox_maps_flutter/mapbox_maps_flutter.dart' as mbx;

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../../core/location/driver_presence_service.dart';
import '../driver/data/driver_repository.dart';
import '../driver/models/driver_dashboard.dart';
import '../missions/data/mission_repository.dart';
import '../missions/models/driver_mission.dart';

/// Écran de suivi temps réel de l'application OVANIE Livreur.
///
/// La carte Mapbox est l'élément principal de l'écran. Le token public Mapbox
/// n'est jamais stocké dans le code Flutter : il est récupéré depuis l'API
/// Laravel authentifiée, qui lit MAPBOX_PUBLIC_TOKEN dans le .env du serveur.
class TrackingScreen extends StatefulWidget {
  const TrackingScreen({
    super.key,
    this.onOpenMission,
  });

  final ValueChanged<DriverMissionDetail>? onOpenMission;

  @override
  State<TrackingScreen> createState() => _TrackingScreenState();
}

class _TrackingScreenState extends State<TrackingScreen>
    with AutomaticKeepAliveClientMixin {
  final Map<String, DriverMissionDetail> _detailCache =
      <String, DriverMissionDetail>{};
  final Map<String, Uint8List> _markerImageCache = <String, Uint8List>{};

  DriverMissionListResult? _listResult;
  DriverMissionDetail? _selectedMission;
  DriverDashboardDriver? _driver;
  Position? _livePosition;
  StreamSubscription<Position>? _positionSubscription;
  Timer? _poller;
  DateTime? _lastMissionLocationSentAt;

  mbx.MapboxMap? _mapboxMap;
  mbx.PointAnnotationManager? _pointAnnotationManager;
  mbx.PolylineAnnotationManager? _polylineAnnotationManager;

  bool _loading = true;
  bool _refreshing = false;
  bool _showAll = false;
  bool _sendingGpsIssue = false;
  bool _satelliteStyle = false;
  bool _mapboxReady = false;
  bool _styleLoaded = false;
  bool _renderingMapData = false;
  bool _hasCenteredOnLivePosition = false;
  bool? _gpsAvailable;
  String? _selectedMissionNumber;
  String? _error;
  String? _mapboxError;
  String _mapboxStyleUri = mbx.MapboxStyles.MAPBOX_STREETS;

  static const _MapCoordinate _fallbackCenter = _MapCoordinate(5.3484, -4.0273);

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _loadMapboxConfiguration();
    _loadDriverVehicle();
    _loadTracking();
    _startLocationStream();
    _poller = Timer.periodic(const Duration(seconds: 12), (_) {
      if (mounted && !_refreshing) {
        _loadTracking(silent: true, keepCamera: true);
      }
      // Le flux de position (presence.positions) ne réémet que lorsqu'un
      // mouvement de véhicule est confirmé (anti-jitter). Sans ce relais, un
      // livreur affecté mais immobile (test, arrêt prolongé) ne renvoie plus
      // jamais sa position à la mission : l'ETA reste bloquée sur "Calcul en
      // cours" et la mission semble figée alors que le GPS fonctionne bien
      // (il alimente déjà la présence générale, visible côté Logistique).
      final stable = DriverPresenceService.instance.stablePosition;
      if (stable != null) {
        _sendMissionLocation(stable);
      }
    });
  }

  @override
  void dispose() {
    _poller?.cancel();
    _positionSubscription?.cancel();
    super.dispose();
  }

  List<DriverMissionSummaryModel> get _activeMissions {
    final missions = _listResult?.missions ?? const <DriverMissionSummaryModel>[];
    final active = missions
        .where((mission) =>
            mission.isAccepted ||
            mission.isCollecting ||
            mission.isLoaded ||
            mission.isInTransit ||
            mission.isArrived ||
            mission.hasIncident)
        .toList(growable: false);

    active.sort((a, b) {
      final aPriority = a.isInProgress ? 0 : 1;
      final bPriority = b.isInProgress ? 0 : 1;
      if (aPriority != bPriority) return aPriority.compareTo(bPriority);
      return 0;
    });
    return active;
  }

  Future<void> _loadMapboxConfiguration() async {
    try {
      final config = await MissionRepository.instance.fetchMapConfig();
      final token = config.accessToken.trim();
      if (token.isEmpty || !token.startsWith('pk.')) {
        throw const OvanieApiException(
          'Le token public Mapbox est absent ou invalide dans la configuration OVANIE.',
        );
      }

      mbx.MapboxOptions.setAccessToken(token);
      if (!mounted) return;
      setState(() {
        _mapboxStyleUri = config.styleUri.trim().isEmpty
            ? mbx.MapboxStyles.MAPBOX_STREETS
            : config.styleUri.trim();
        _mapboxReady = true;
        _mapboxError = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _mapboxReady = false;
        _mapboxError = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _loadDriverVehicle() async {
    try {
      final dashboard = await DriverRepository.instance.dashboard();
      if (!mounted) return;
      setState(() => _driver = dashboard.driver);
      await _syncMapData(frameCamera: false);
    } catch (_) {
      // Le suivi reste utilisable si le tableau de bord est momentanément indisponible.
      // Dans ce cas, aucun autre type de véhicule n'est inventé à la place du livreur.
    }
  }

  Future<void> _loadTracking({
    bool silent = false,
    bool keepCamera = false,
  }) async {
    if (_refreshing) return;
    _refreshing = true;
    if (!silent && mounted) setState(() => _loading = true);

    try {
      final result = await MissionRepository.instance.fetchMissions();
      if (!mounted) return;

      final active = result.missions
          .where((mission) =>
              mission.isAccepted ||
              mission.isCollecting ||
              mission.isLoaded ||
              mission.isInTransit ||
              mission.isArrived ||
              mission.hasIncident)
          .toList(growable: false);

      String? selectedNumber = _selectedMissionNumber;
      if (selectedNumber == null ||
          !active.any((mission) => mission.missionNumber == selectedNumber)) {
        final running = active.where((mission) => mission.isInProgress).toList();
        selectedNumber =
            (running.isNotEmpty ? running.first : active.firstOrNull)
                ?.missionNumber;
      }

      DriverMissionDetail? detail;
      if (selectedNumber != null) {
        detail = await MissionRepository.instance.fetchMission(selectedNumber);
        _detailCache[selectedNumber] = detail;
      }

      if (!mounted) return;
      setState(() {
        _listResult = result;
        _selectedMissionNumber = selectedNumber;
        _selectedMission = detail;
        _error = null;
      });

      if (_showAll) {
        await _loadAllActiveDetails(active);
      }

      await _syncMapData(frameCamera: !keepCamera);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      _refreshing = false;
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadAllActiveDetails(
    List<DriverMissionSummaryModel> summaries,
  ) async {
    await Future.wait(summaries.map((summary) async {
      if (_detailCache.containsKey(summary.missionNumber)) return;
      try {
        final detail =
            await MissionRepository.instance.fetchMission(summary.missionNumber);
        _detailCache[summary.missionNumber] = detail;
      } catch (_) {
        // Une autre mission ne doit pas bloquer la mission actuellement suivie.
      }
    }));
    if (mounted) setState(() {});
  }

  Future<void> _startLocationStream() async {
    // Le GPS est maintenant centralisé dans DriverPresenceService. L'écran
    // Suivi n'ouvre plus un second flux Android indépendant : le même point
    // stabilisé est affiché ici et envoyé à l'espace Logistique.
    final presence = DriverPresenceService.instance;

    final initial = presence.stablePosition;
    if (initial != null) {
      _onPosition(initial);
    }

    await _positionSubscription?.cancel();
    _positionSubscription = presence.positions.listen(
      _onPosition,
      onError: (_) {},
    );

    try {
      await presence.start();
      if (!mounted) return;
      final stable = presence.stablePosition;
      if (stable != null && _livePosition == null) {
        _onPosition(stable);
      } else {
        setState(() => _gpsAvailable = presence.gpsAvailable);
      }
    } catch (_) {
      if (mounted) setState(() => _gpsAvailable = false);
    }
  }

  void _onPosition(Position position) {
    if (!mounted) return;

    // [position] est déjà filtrée par DriverPresenceService : elle ne change
    // que lorsqu'un mouvement de véhicule a été confirmé. Aucun second filtre
    // local ne vient créer une position différente de celle vue en Logistique.
    final shouldCenter = !_hasCenteredOnLivePosition && _activeMissions.isEmpty;
    setState(() {
      _livePosition = position;
      _gpsAvailable = true;
      if (shouldCenter) _hasCenteredOnLivePosition = true;
    });

    unawaited(_syncMapData(frameCamera: false));
    if (shouldCenter && _mapboxReady) {
      _recenterOnDriver();
    }
    _sendMissionLocation(position);
  }

  Future<void> _sendMissionLocation(Position position) async {
    final mission = _selectedMission;
    if (mission == null || mission.isDelivered || mission.isRejected) return;

    final now = DateTime.now();
    if (_lastMissionLocationSentAt != null &&
        now.difference(_lastMissionLocationSentAt!) <
            const Duration(seconds: 10)) {
      return;
    }
    _lastMissionLocationSentAt = now;

    try {
      await MissionRepository.instance.recordLocation(
        mission.missionNumber,
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy.isFinite ? position.accuracy : null,
        speed: position.speed.isFinite ? position.speed : null,
        heading: position.heading.isFinite ? position.heading : null,
      );
      if (!mounted) return;
      final refreshed = await MissionRepository.instance
          .fetchMission(mission.missionNumber);
      if (!mounted) return;
      setState(() {
        _selectedMission = refreshed;
        _detailCache[refreshed.missionNumber] = refreshed;
      });
      await _syncMapData(frameCamera: false);
    } catch (_) {
      // Le prochain point GPS réessaiera automatiquement.
    }
  }

  Future<void> _selectMission(String missionNumber) async {
    if (_selectedMissionNumber == missionNumber && !_showAll) return;
    setState(() {
      _showAll = false;
      _selectedMissionNumber = missionNumber;
      _selectedMission = _detailCache[missionNumber];
      _loading = _selectedMission == null;
    });

    try {
      final detail = _detailCache[missionNumber] ??
          await MissionRepository.instance.fetchMission(missionNumber);
      if (!mounted) return;
      setState(() {
        _selectedMission = detail;
        _detailCache[missionNumber] = detail;
        _loading = false;
      });
      await _syncMapData(frameCamera: true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _showMissionPicker() async {
    final active = _activeMissions;
    if (active.length <= 1) return;

    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 2, 18, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Choisir une mission',
                style: TextStyle(
                  color: Color(0xFF0F1838),
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 10),
              ...active.map((mission) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      backgroundColor:
                          mission.missionNumber == _selectedMissionNumber
                              ? OvanieColors.green
                              : OvanieColors.greenLight,
                      child: Icon(
                        Icons.local_shipping_rounded,
                        color: mission.missionNumber == _selectedMissionNumber
                            ? Colors.white
                            : OvanieColors.green,
                      ),
                    ),
                    title: Text(
                      'Mission ${mission.missionNumber}',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(mission.displayDestination),
                    trailing: mission.missionNumber == _selectedMissionNumber
                        ? const Icon(Icons.check_circle,
                            color: OvanieColors.green)
                        : null,
                    onTap: () => Navigator.pop(context, mission.missionNumber),
                  )),
            ],
          ),
        ),
      ),
    );

    if (selected != null) await _selectMission(selected);
  }

  Future<void> _showAllMissions() async {
    final active = _activeMissions;
    setState(() => _showAll = true);
    await _loadAllActiveDetails(active);
    await _syncMapData(frameCamera: true);
  }

  List<DriverMissionDetail> get _visibleDetails {
    if (!_showAll) {
      final detail = _selectedMission;
      return detail == null ? const [] : [detail];
    }
    return _activeMissions
        .map((summary) => _detailCache[summary.missionNumber])
        .whereType<DriverMissionDetail>()
        .toList(growable: false);
  }

  List<_MapCoordinate> _allCameraPoints() {
    final result = <_MapCoordinate>[];
    for (final detail in _visibleDetails) {
      result.addAll(detail.mapPoints.map(
        (point) => _MapCoordinate(point.latitude, point.longitude),
      ));
      result.addAll(detail.routePlan?.geometry.map(
            (point) => _MapCoordinate(point.latitude, point.longitude),
          ) ??
          const <_MapCoordinate>[]);
    }
    final live = _livePosition;
    if (live != null && !_showAll) {
      result.add(_MapCoordinate(live.latitude, live.longitude));
    }
    return result;
  }

  _MapCoordinate _centerOf(List<_MapCoordinate> points) {
    if (points.isEmpty) return _fallbackCenter;
    var lat = 0.0;
    var lng = 0.0;
    for (final point in points) {
      lat += point.latitude;
      lng += point.longitude;
    }
    return _MapCoordinate(lat / points.length, lng / points.length);
  }

  double _zoomFor(List<_MapCoordinate> points) {
    if (points.length < 2) return 15.2;
    var minLat = points.first.latitude;
    var maxLat = points.first.latitude;
    var minLng = points.first.longitude;
    var maxLng = points.first.longitude;
    for (final point in points.skip(1)) {
      minLat = math.min(minLat, point.latitude);
      maxLat = math.max(maxLat, point.latitude);
      minLng = math.min(minLng, point.longitude);
      maxLng = math.max(maxLng, point.longitude);
    }
    final span = math.max(maxLat - minLat, maxLng - minLng);
    if (span <= .004) return 15.8;
    if (span <= .009) return 14.9;
    if (span <= .018) return 14.0;
    if (span <= .035) return 13.0;
    if (span <= .075) return 11.8;
    if (span <= .16) return 10.6;
    if (span <= .35) return 9.2;
    return 7.4;
  }

  Future<void> _onMapCreated(mbx.MapboxMap mapboxMap) async {
    _mapboxMap = mapboxMap;
    await _applyMapOrnaments(mapboxMap);
    await _disableNativeLocationPuck();
  }

  /// Réglages visuels (échelle, boussole, logo) de la carte.
  ///
  /// Réappliqués après le chargement du style (pas seulement à la création
  /// de la carte) car les vues natives Mapbox ne sont pas toujours prêtes à
  /// temps dans `_onMapCreated` : sans ce second appel, l'échelle en bas à
  /// droite pouvait ne jamais s'afficher.
  Future<void> _applyMapOrnaments(mbx.MapboxMap map) async {
    await Future.wait([
      map.compass.updateSettings(
        mbx.CompassSettings(enabled: false),
      ),
      map.scaleBar.updateSettings(
        mbx.ScaleBarSettings(
          enabled: true,
          position: mbx.OrnamentPosition.BOTTOM_RIGHT,
          marginBottom: 90,
          marginRight: 10,
        ),
      ),
      map.logo.updateSettings(
        mbx.LogoSettings(position: mbx.OrnamentPosition.BOTTOM_LEFT, marginBottom: 160, marginLeft: 12),
      ),
      map.attribution.updateSettings(
        mbx.AttributionSettings(position: mbx.OrnamentPosition.BOTTOM_LEFT, marginBottom: 160, marginLeft: 92),
      ),
    ]);
  }

  Future<void> _onStyleLoaded() async {
    _styleLoaded = true;
    final map = _mapboxMap;
    if (map == null) return;

    _pointAnnotationManager ??=
        await map.annotations.createPointAnnotationManager();
    _polylineAnnotationManager ??=
        await map.annotations.createPolylineAnnotationManager();

    await _pointAnnotationManager?.setIconAllowOverlap(true);
    await _pointAnnotationManager?.setTextAllowOverlap(true);
    await _disableNativeLocationPuck();
    await _applyMapOrnaments(map);
    await _syncMapData(frameCamera: true);
  }

  Future<void> _disableNativeLocationPuck() async {
    final map = _mapboxMap;
    if (map == null) return;
    try {
      // Le point bleu Mapbox est volontairement désactivé. La position du
      // livreur est représentée par son vrai type de véhicule OVANIE, comme
      // dans l'espace Logistique.
      await map.location.updateSettings(
        mbx.LocationComponentSettings(enabled: false),
      );
    } catch (_) {
      // La carte reste utilisable même si le composant de localisation natif
      // n'est pas encore initialisé par Mapbox.
    }
  }

  Future<void> _syncMapData({required bool frameCamera}) async {
    if (!_mapboxReady || !_styleLoaded || _renderingMapData) return;
    final map = _mapboxMap;
    final pointManager = _pointAnnotationManager;
    final lineManager = _polylineAnnotationManager;
    if (map == null || pointManager == null || lineManager == null) return;

    _renderingMapData = true;
    try {
      await lineManager.deleteAll();
      await pointManager.deleteAll();

      final details = _visibleDetails;
      final routeOptions = <mbx.PolylineAnnotationOptions>[];
      final markerOptions = <mbx.PointAnnotationOptions>[];

      DriverMissionGeoPoint? backendDriverPoint;
      for (final detail in details) {
        final geometry =
            detail.routePlan?.geometry ?? const <DriverMissionCoordinate>[];
        if (geometry.length >= 2) {
          routeOptions.add(
            mbx.PolylineAnnotationOptions(
              geometry: mbx.LineString(
                coordinates: geometry
                    .map((point) => mbx.Position(
                          point.longitude,
                          point.latitude,
                        ))
                    .toList(growable: false),
              ),
              lineColor: OvanieColors.green.value,
              lineWidth: 6.0,
              lineBorderColor: Colors.white.value,
              lineBorderWidth: 2.0,
              lineJoin: mbx.LineJoin.ROUND,
              lineOpacity: .96,
            ),
          );
        }

        for (final point in detail.mapPoints) {
          if (point.type == 'driver') {
            backendDriverPoint ??= point;
            continue;
          }
          markerOptions.add(await _annotationForPoint(detail, point));
        }
      }

      // Un seul marqueur représente le livreur, même lorsque plusieurs
      // missions sont affichées. La position GPS du téléphone est prioritaire ;
      // la dernière position backend sert seulement de secours.
      final live = _livePosition;
      if (live != null) {
        // Sur un téléphone immobile (test) ou sans boussole fiable, le cap
        // GPS brut reste souvent à 0 ou invalide : l'icône semblait figée
        // dans une direction qui ne correspondait à rien. À défaut de cap
        // fiable, on oriente le véhicule vers le prochain point du tracé
        // calculé (donc vers la boutique/le client), ce qui reste correct
        // même sans mouvement réel.
        // Un cap exactement à 0 n'est presque jamais une vraie orientation
        // nord sur un téléphone : c'est l'absence de donnée (immobile, pas
        // de boussole). On le traite alors comme invalide.
        double? heading = live.heading.isFinite && live.heading.abs() > 0.05
            ? live.heading
            : null;
        if (heading == null) {
          for (final detail in details) {
            final geometry =
                detail.routePlan?.geometry ?? const <DriverMissionCoordinate>[];
            if (geometry.length >= 2) {
              heading = _bearingBetween(
                live.latitude,
                live.longitude,
                geometry[1].latitude,
                geometry[1].longitude,
              );
              break;
            }
          }
        }
        markerOptions.add(
          await _driverVehicleAnnotation(
            latitude: live.latitude,
            longitude: live.longitude,
            heading: heading,
          ),
        );
      } else if (backendDriverPoint != null) {
        markerOptions.add(
          await _driverVehicleAnnotation(
            latitude: backendDriverPoint.latitude,
            longitude: backendDriverPoint.longitude,
          ),
        );
      }

      if (routeOptions.isNotEmpty) {
        await lineManager.createMulti(routeOptions);
      }
      if (markerOptions.isNotEmpty) {
        await pointManager.createMulti(markerOptions);
      }

      if (frameCamera) {
        await _frameVisibleData();
      }
    } catch (_) {
      // Une erreur d'annotation ne doit pas masquer toute la carte Mapbox.
    } finally {
      _renderingMapData = false;
    }
  }

  Future<mbx.PointAnnotationOptions> _annotationForPoint(
    DriverMissionDetail detail,
    DriverMissionGeoPoint point,
  ) async {
    final pickup = point.type == 'pickup'
        ? detail.pickupStops.where((stop) => stop.id == point.id).firstOrNull
        : null;
    final current = pickup != null &&
        (pickup.current || detail.nextPickupStop?.id == pickup.id);

    Color color;
    IconData icon;
    String label;

    switch (point.type) {
      case 'destination':
        color = const Color(0xFFF43F3F);
        icon = Icons.location_on_rounded;
        label = 'Client\n${_shortLabel(point.address, point.name)}';
        break;
      case 'driver':
        color = OvanieColors.green;
        icon = Icons.local_shipping_rounded;
        label = 'Votre position';
        break;
      case 'pickup':
        if (point.completed || pickup?.completed == true) {
          color = OvanieColors.green;
          icon = Icons.check_rounded;
        } else if (current) {
          color = OvanieColors.green;
          icon = Icons.navigation_rounded;
        } else {
          color = const Color(0xFF66758E);
          icon = Icons.inventory_2_outlined;
        }
        final index = pickup?.index ?? 0;
        final title = index > 0 ? 'Point $index' : point.name;
        label = '$title\n${_shortLabel(pickup?.locationLabel, point.address)}';
        break;
      default:
        color = const Color(0xFF66758E);
        icon = Icons.place_rounded;
        label = point.name;
    }

    final image = await _markerImage(
      key: '${point.type}-${color.value}-${icon.codePoint}',
      color: color,
      icon: icon,
    );

    return mbx.PointAnnotationOptions(
      geometry: mbx.Point(
        coordinates: mbx.Position(point.longitude, point.latitude),
      ),
      image: image,
      iconSize: point.type == 'driver' ? .82 : .74,
      iconAnchor: mbx.IconAnchor.CENTER,
      textField: label,
      textSize: 12.0,
      textColor: const Color(0xFF13203D).value,
      textHaloColor: Colors.white.value,
      textHaloWidth: 2.0,
      textAnchor: mbx.TextAnchor.TOP,
      textOffset: const <double?>[0, 1.6],
      textMaxWidth: 12,
      symbolSortKey: current || point.type == 'driver' ? 10 : 2,
    );
  }

  double _bearingBetween(
    double fromLat,
    double fromLng,
    double toLat,
    double toLng,
  ) {
    final lat1 = fromLat * math.pi / 180;
    final lat2 = toLat * math.pi / 180;
    final deltaLng = (toLng - fromLng) * math.pi / 180;
    final y = math.sin(deltaLng) * math.cos(lat2);
    final x = math.cos(lat1) * math.sin(lat2) -
        math.sin(lat1) * math.cos(lat2) * math.cos(deltaLng);
    final bearing = math.atan2(y, x) * 180 / math.pi;
    return (bearing + 360) % 360;
  }

  Future<mbx.PointAnnotationOptions> _driverVehicleAnnotation({
    required double latitude,
    required double longitude,
    double? heading,
  }) async {
    final driver = _driver;
    final vehicleCode = (driver?.vehicleCode ?? '').trim();
    final vehicleLabel = (driver?.vehicle ?? '').trim();
    final assetPath = _vehicleTrackingAssetPath(
      code: vehicleCode,
      label: vehicleLabel,
    );

    Uint8List image;
    double iconSize;
    // Illustration vue de profil (regarde vers la droite/l'est par défaut) :
    // on la retourne à l'horizontale plutôt que de la faire pivoter.
    final flip = heading != null && math.sin(heading * math.pi / 180) < 0;

    if (assetPath != null) {
      try {
        // Même source d'image que logistics-dashboard-map.js : on utilise les
        // PNG embarqués de l'espace Logistique, puis on applique la même
        // couleur véhicule, la plaque et le point de présence.
        image = await _logisticsVehicleMarkerImage(
          assetPath: assetPath,
          // Teinte uniquement si le livreur a une couleur de véhicule
          // renseignée (hex exact en priorité, sinon le libellé). Sans
          // couleur connue, l'image garde ses couleurs d'origine au lieu
          // d'être délavée en gris.
          color: _parseVehicleHexColor(driver?.vehicleColorHex) ??
              _vehicleColorFromLabel(driver?.vehicleColor),
          plate: (driver?.vehiclePlate ?? '').trim(),
          online: _gpsAvailable == true || (driver?.isOnline ?? false),
          flip: flip,
        );
        iconSize = .72;
      } catch (_) {
        image = await _markerImage(
          key: 'driver-fallback-$vehicleCode',
          color: const Color(0xFF7D8998),
          icon: _vehicleIcon(vehicleLabel),
        );
        iconSize = .76;
      }
    } else {
      image = await _markerImage(
        key: 'driver-generic-$vehicleCode',
        color: const Color(0xFF7D8998),
        icon: _vehicleIcon(vehicleLabel),
      );
      iconSize = .76;
    }

    return mbx.PointAnnotationOptions(
      geometry: mbx.Point(
        coordinates: mbx.Position(longitude, latitude),
      ),
      image: image,
      iconSize: iconSize,
      iconAnchor: mbx.IconAnchor.CENTER,
      symbolSortKey: 20,
    );
  }

  String? _vehicleTrackingAssetPath({
    required String code,
    required String label,
  }) {
    final value = (code.isNotEmpty ? code : label)
        .toLowerCase()
        .replaceAll('-', '_')
        .replaceAll(' ', '_')
        .trim();

    if (value.contains('tricycle') || value.contains('triporteur')) {
      return 'assets/logistics-tricycle.png';
    }
    if (value.contains('moto') || value.contains('scooter')) {
      return 'assets/logistics-moto.png';
    }
    if (value.contains('pickup') || value.contains('pick_up')) {
      return 'assets/logistics-pickup.png';
    }
    if (value.contains('camion_10t') ||
        value.contains('10t') ||
        value.contains('10_t') ||
        value.contains('truck10')) {
      return 'assets/logistics-camion-10t.png';
    }
    if (value.contains('camion_3t') ||
        value.contains('3t') ||
        value.contains('3_t') ||
        value.contains('truck3')) {
      return 'assets/logistics-camion-3t.png';
    }
    return null;
  }

  IconData _vehicleIcon(String type) {
    final value = type.toLowerCase();
    if (value.contains('moto') || value.contains('scooter')) {
      return Icons.two_wheeler_rounded;
    }
    if (value.contains('tricycle') || value.contains('triporteur')) {
      return Icons.electric_rickshaw_rounded;
    }
    return Icons.local_shipping_rounded;
  }

  Color? _vehicleColorFromLabel(String? label) {
    final value = (label ?? '').toLowerCase().trim();
    if (value.isEmpty) return null;
    if (value.contains('argent') || value.contains('gris clair')) {
      return const Color(0xFFA7ADB3);
    }
    if (value.contains('gris foncé') || value.contains('gris fonce')) {
      return const Color(0xFF5E646B);
    }
    if (value == 'gris') return const Color(0xFF80868D);
    if (value.contains('noir')) return const Color(0xFF202428);
    if (value.contains('blanc')) return const Color(0xFFE9EDF0);
    if (value.contains('rouge')) return const Color(0xFFD9342B);
    if (value.contains('orange')) return const Color(0xFFF28B24);
    if (value.contains('jaune')) return const Color(0xFFE3B316);
    if (value.contains('vert')) return const Color(0xFF159447);
    if (value.contains('turquoise')) return const Color(0xFF1B9C9A);
    if (value.contains('bleu')) return const Color(0xFF2D63B7);
    if (value.contains('violet')) return const Color(0xFF7250A8);
    if (value.contains('rose') || value.contains('bordeaux')) {
      return const Color(0xFFA53757);
    }
    return null;
  }

  Color? _parseVehicleHexColor(String? value) {
    final input = (value ?? '').replaceAll('#', '').trim();
    if (input.length != 6) return null;
    final parsed = int.tryParse(input, radix: 16);
    return parsed == null ? null : Color(0xFF000000 | parsed);
  }

  /// Reproduit le marqueur de l'espace Logistique dans une seule image Mapbox :
  /// véhicule 3D coloré, plaque en bleu nuit et point de présence.
  Future<Uint8List> _logisticsVehicleMarkerImage({
    required String assetPath,
    required Color? color,
    required String plate,
    required bool online,
    bool flip = false,
  }) async {
    final cacheKey =
        'logistics-marker:$assetPath:${color?.value}:$plate:$online:$flip';
    final cached = _markerImageCache[cacheKey];
    if (cached != null) return cached;

    final data = await rootBundle.load(assetPath);
    final bytes = data.buffer.asUint8List(data.offsetInBytes, data.lengthInBytes);
    final codec = await ui.instantiateImageCodec(bytes);
    final frame = await codec.getNextFrame();
    final source = frame.image;

    const canvasWidth = 160.0;
    const canvasHeight = 104.0;
    const vehicleBox = Rect.fromLTWH(8, 4, 144, 96);

    final scale = math.min(
      vehicleBox.width / source.width,
      vehicleBox.height / source.height,
    );
    final drawWidth = source.width * scale;
    final drawHeight = source.height * scale;
    final destination = Rect.fromLTWH(
      vehicleBox.left + (vehicleBox.width - drawWidth) / 2,
      vehicleBox.top + (vehicleBox.height - drawHeight) / 2,
      drawWidth,
      drawHeight,
    );

    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final sourceRect = Rect.fromLTWH(
      0,
      0,
      source.width.toDouble(),
      source.height.toDouble(),
    );

    // L'illustration est vue de profil, pas du dessus : la faire pivoter à un
    // cap arbitraire la ferait apparaître penchée/couchée. On se contente
    // donc de la retourner à l'horizontale selon la direction est/ouest.
    if (flip) {
      canvas.save();
      canvas.translate(canvasWidth, 0);
      canvas.scale(-1, 1);
    }

    // Couleur exacte du véhicule du livreur si elle est connue ; sinon les
    // couleurs d'origine du PNG sont conservées.
    if (color == null) {
      canvas.drawImageRect(
        source,
        sourceRect,
        destination,
        Paint()..filterQuality = FilterQuality.high,
      );
    } else {
      // ColorFilter.mode(color, BlendMode.color) peint tout le rectangle de
      // destination en opaque (bug connu de Skia sur certains appareils),
      // pas seulement la silhouette du véhicule : on isole le rendu dans un
      // calque puis on le redécoupe avec l'alpha d'origine du PNG (dstIn)
      // pour ne garder la teinte que là où le véhicule est réellement dessiné.
      canvas.saveLayer(destination, Paint());
      canvas.drawImageRect(
        source,
        sourceRect,
        destination,
        Paint()
          ..filterQuality = FilterQuality.high
          ..colorFilter = ColorFilter.mode(color, BlendMode.color),
      );
      canvas.drawImageRect(
        source,
        sourceRect,
        destination,
        Paint()
          ..filterQuality = FilterQuality.high
          ..blendMode = BlendMode.dstIn,
      );
      canvas.restore();
    }

    if (flip) canvas.restore();

    final image = await recorder
        .endRecording()
        .toImage(canvasWidth.toInt(), canvasHeight.toInt());
    final png = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    source.dispose();

    final result = png!.buffer.asUint8List();
    _markerImageCache[cacheKey] = result;
    return result;
  }

  String _shortLabel(String? primary, String? fallback) {
    final value = (primary ?? '').trim().isNotEmpty
        ? primary!.trim()
        : (fallback ?? '').trim();
    if (value.length <= 30) return value;
    return '${value.substring(0, 27)}…';
  }

  Future<Uint8List> _markerImage({
    required String key,
    required Color color,
    required IconData icon,
  }) async {
    final cached = _markerImageCache[key];
    if (cached != null) return cached;

    const size = 112.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final center = const Offset(size / 2, size / 2);

    canvas.drawCircle(
      center,
      46,
      Paint()
        ..color = Colors.black.withValues(alpha: .14)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 8),
    );
    canvas.drawCircle(center, 43, Paint()..color = Colors.white);
    canvas.drawCircle(center, 36, Paint()..color = color);

    final painter = TextPainter(
      textDirection: TextDirection.ltr,
      text: TextSpan(
        text: String.fromCharCode(icon.codePoint),
        style: TextStyle(
          fontSize: 40,
          fontFamily: icon.fontFamily,
          color: Colors.white,
        ),
      ),
    )..layout();
    painter.paint(
      canvas,
      Offset(
        center.dx - painter.width / 2,
        center.dy - painter.height / 2,
      ),
    );

    final image = await recorder.endRecording().toImage(size.toInt(), size.toInt());
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    final bytes = data!.buffer.asUint8List();
    _markerImageCache[key] = bytes;
    return bytes;
  }

  Future<void> _frameVisibleData() async {
    final map = _mapboxMap;
    if (map == null) return;
    final points = _allCameraPoints();
    if (points.isEmpty) {
      final live = _livePosition;
      final center = live == null
          ? _fallbackCenter
          : _MapCoordinate(live.latitude, live.longitude);
      await map.easeTo(
        mbx.CameraOptions(
          center: mbx.Point(
            coordinates: mbx.Position(center.longitude, center.latitude),
          ),
          zoom: live == null ? 12.0 : 15.5,
          pitch: 0,
        ),
        mbx.MapAnimationOptions(duration: 550, startDelay: 0),
      );
      return;
    }

    final mbxPoints = points
        .map((point) => mbx.Point(
              coordinates: mbx.Position(point.longitude, point.latitude),
            ))
        .toList(growable: false);

    if (mbxPoints.length == 1) {
      await map.easeTo(
        mbx.CameraOptions(center: mbxPoints.first, zoom: 15.5, pitch: 0),
        mbx.MapAnimationOptions(duration: 550, startDelay: 0),
      );
      return;
    }

    try {
      final camera = await map.cameraForCoordinates(
        mbxPoints,
        mbx.MbxEdgeInsets(
          top: 150,
          left: 54,
          bottom: _showAll ? 180 : 280,
          right: 54,
        ),
        null,
        0,
      );
      await map.easeTo(
        camera,
        mbx.MapAnimationOptions(duration: 650, startDelay: 0),
      );
    } catch (_) {
      final center = _centerOf(points);
      await map.easeTo(
        mbx.CameraOptions(
          center: mbx.Point(
            coordinates: mbx.Position(center.longitude, center.latitude),
          ),
          zoom: _zoomFor(points),
        ),
        mbx.MapAnimationOptions(duration: 550, startDelay: 0),
      );
    }
  }

  Future<void> _recenterOnDriver() async {
    final map = _mapboxMap;
    if (map == null) return;

    final live = _livePosition;
    if (live != null) {
      await map.easeTo(
        mbx.CameraOptions(
          center: mbx.Point(
            coordinates: mbx.Position(live.longitude, live.latitude),
          ),
          zoom: 16.0,
          bearing: live.heading.isFinite && live.heading >= 0
              ? live.heading
              : 0,
          pitch: 20,
        ),
        mbx.MapAnimationOptions(duration: 600, startDelay: 0),
      );
      return;
    }

    for (final point in _selectedMission?.mapPoints ??
        const <DriverMissionGeoPoint>[]) {
      if (point.type == 'driver') {
        await map.easeTo(
          mbx.CameraOptions(
            center: mbx.Point(
              coordinates: mbx.Position(point.longitude, point.latitude),
            ),
            zoom: 15.7,
          ),
          mbx.MapAnimationOptions(duration: 600, startDelay: 0),
        );
        return;
      }
    }

    await _frameVisibleData();
  }

  Future<void> _toggleMapStyle() async {
    final map = _mapboxMap;
    if (map == null) return;
    setState(() => _satelliteStyle = !_satelliteStyle);
    _styleLoaded = false;
    _pointAnnotationManager = null;
    _polylineAnnotationManager = null;
    final uri = _satelliteStyle
        ? mbx.MapboxStyles.SATELLITE_STREETS
        : _mapboxStyleUri;
    await map.loadStyleURI(uri);
  }

  Future<void> _showGpsProblemSheet() async {
    final mission = _selectedMission;
    if (mission == null || _sendingGpsIssue) return;

    final reason = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
      ),
      builder: (context) {
        const reasons = <(String, String, IconData)>[
          ('permission_denied', 'Autorisation GPS refusée', Icons.location_off),
          ('no_signal', 'Signal GPS indisponible', Icons.gps_off_rounded),
          ('device_issue', 'Problème avec le téléphone', Icons.phone_android_rounded),
          ('battery_saving', 'Économie de batterie active', Icons.battery_saver_rounded),
          ('other', 'Autre problème GPS', Icons.warning_amber_rounded),
        ];
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 2, 18, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Problème GPS',
                  style: TextStyle(
                    color: Color(0xFF0F1838),
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Indiquez pourquoi votre position ne peut pas être suivie.',
                  style: TextStyle(color: OvanieColors.muted),
                ),
                const SizedBox(height: 10),
                ...reasons.map((item) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(item.$3, color: OvanieColors.green),
                      title: Text(item.$2,
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                      trailing: const Icon(Icons.chevron_right_rounded),
                      onTap: () => Navigator.pop(context, item.$1),
                    )),
              ],
            ),
          ),
        );
      },
    );

    if (reason == null || !mounted) return;
    setState(() => _sendingGpsIssue = true);
    try {
      await MissionRepository.instance.markGpsUnavailable(
        mission.missionNumber,
        reason: reason,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Le problème GPS a été signalé à OVANIE Logistics.'),
        ),
      );
      await _loadTracking(silent: true, keepCamera: true);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ApiClient.friendlyError(error))),
      );
    } finally {
      if (mounted) setState(() => _sendingGpsIssue = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final initialPoints = _allCameraPoints();
    final initialCenter = initialPoints.isEmpty
        ? (_livePosition == null
            ? _fallbackCenter
            : _MapCoordinate(
                _livePosition!.latitude,
                _livePosition!.longitude,
              ))
        : _centerOf(initialPoints);
    final initialZoom = initialPoints.isEmpty
        ? (_livePosition == null ? 12.0 : 15.2)
        : _zoomFor(initialPoints);

    return ColoredBox(
      color: OvanieColors.background,
      child: Column(
        children: [
          _TrackingHeader(
            notificationCount: _listResult?.unreadNotifications ?? 0,
            onNotifications: () {
              final count = _listResult?.unreadNotifications ?? 0;
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(count == 0
                      ? 'Aucune nouvelle notification.'
                      : '$count notification(s) non lue(s).'),
                ),
              );
            },
          ),
          Expanded(
            child: Stack(
              children: [
                Positioned.fill(
                  child: _mapboxReady
                      ? mbx.MapWidget(
                          key: const ValueKey('ovanie-mapbox-tracking'),
                          styleUri: _satelliteStyle
                              ? mbx.MapboxStyles.SATELLITE_STREETS
                              : _mapboxStyleUri,
                          cameraOptions: mbx.CameraOptions(
                            center: mbx.Point(
                              coordinates: mbx.Position(
                                initialCenter.longitude,
                                initialCenter.latitude,
                              ),
                            ),
                            zoom: initialZoom,
                            pitch: 0,
                          ),
                          onMapCreated: _onMapCreated,
                          onStyleLoadedListener: (_) => _onStyleLoaded(),
                        )
                      : _MapboxWaitingView(
                          message: _mapboxError,
                          onRetry: _loadMapboxConfiguration,
                        ),
                ),
                Positioned(
                  left: 0,
                  right: 0,
                  top: 0,
                  child: _MissionSelector(
                    showAll: _showAll,
                    mission: _selectedMission,
                    activeCount: _activeMissions.length,
                    onAll: _showAllMissions,
                    onMission: () {
                      if (_showAll && _selectedMissionNumber != null) {
                        _selectMission(_selectedMissionNumber!);
                      } else {
                        _showMissionPicker();
                      }
                    },
                  ),
                ),
                if (!_showAll)
                  Positioned(
                    left: 16,
                    top: 76,
                    child: _GpsStatusCard(
                      position: _livePosition,
                      mission: _selectedMission,
                      localAvailable: _gpsAvailable,
                    ),
                  ),
                if (!_showAll && _selectedMission != null)
                  Positioned(
                    right: 16,
                    top: 76,
                    child: _NextPointCard(mission: _selectedMission!),
                  ),
                Positioned(
                  right: 16,
                  top: 144,
                  child: Column(
                    children: [
                      _MapRoundButton(
                        icon: _satelliteStyle
                            ? Icons.map_outlined
                            : Icons.layers_outlined,
                        onTap: () {
                          if (_mapboxReady) _toggleMapStyle();
                        },
                      ),
                      const SizedBox(height: 10),
                      _MapRoundButton(
                        icon: Icons.my_location_rounded,
                        onTap: () {
                          if (_mapboxReady) _recenterOnDriver();
                        },
                      ),
                    ],
                  ),
                ),
                if (_loading && _selectedMission == null)
                  const Center(
                    child: Card(
                      child: Padding(
                        padding: EdgeInsets.all(18),
                        child: CircularProgressIndicator(
                          color: OvanieColors.green,
                        ),
                      ),
                    ),
                  ),
                if (_error != null && _selectedMission == null && !_loading)
                  Center(
                    child: _TrackingErrorCard(
                      message: _error!,
                      onRetry: () => _loadTracking(),
                    ),
                  ),
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: _showAll
                      ? _AllMissionsSheet(
                          activeMissions: _activeMissions,
                          onSelect: _selectMission,
                        )
                      : _MissionTrackingSheet(
                          mission: _selectedMission,
                          onOpenMission: _selectedMission == null
                              ? null
                              : () => widget.onOpenMission
                                  ?.call(_selectedMission!),
                          onGpsProblem: _selectedMission == null
                              ? null
                              : _showGpsProblemSheet,
                          gpsBusy: _sendingGpsIssue,
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

class _MapCoordinate {
  const _MapCoordinate(this.latitude, this.longitude);

  final double latitude;
  final double longitude;
}

class _MapboxWaitingView extends StatelessWidget {
  const _MapboxWaitingView({
    required this.message,
    required this.onRetry,
  });

  final String? message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: const Color(0xFFEFF6F3),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 360),
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x16000000),
                    blurRadius: 18,
                    offset: Offset(0, 8),
                  ),
                ],
              ),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.map_rounded,
                      size: 42,
                      color: OvanieColors.green,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      message == null
                          ? 'Chargement de la carte Mapbox…'
                          : 'Carte Mapbox indisponible',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Color(0xFF0F1838),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (message != null) ...[
                      const SizedBox(height: 7),
                      Text(
                        message!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: OvanieColors.muted),
                      ),
                      const SizedBox(height: 14),
                      FilledButton.icon(
                        onPressed: onRetry,
                        icon: const Icon(Icons.refresh_rounded),
                        label: const Text('Réessayer'),
                      ),
                    ] else ...[
                      const SizedBox(height: 14),
                      const CircularProgressIndicator(
                        color: OvanieColors.green,
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _TrackingHeader extends StatelessWidget {
  const _TrackingHeader({
    required this.notificationCount,
    required this.onNotifications,
  });

  final int notificationCount;
  final VoidCallback onNotifications;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF008C51), Color(0xFF00A765)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 18, 16),
          child: Stack(
            children: [
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        'OVANIE',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                          letterSpacing: .2,
                        ),
                      ),
                      SizedBox(width: 5),
                      Text(
                        'Logistics',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 15.5,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                  SizedBox(height: 8),
                  Text(
                    'Suivi des livraisons',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 26,
                      height: 1.04,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  SizedBox(height: 3),
                  Text(
                    'Suivi en temps réel',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 15.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
              Positioned(
                right: 0,
                top: 2,
                child: InkWell(
                  onTap: onNotifications,
                  borderRadius: BorderRadius.circular(30),
                  child: SizedBox(
                    width: 50,
                    height: 50,
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        const Align(
                          alignment: Alignment.center,
                          child: Icon(
                            Icons.notifications_none_rounded,
                            color: Colors.white,
                            size: 32,
                          ),
                        ),
                        if (notificationCount > 0)
                          Positioned(
                            right: 0,
                            top: 0,
                            child: Container(
                              constraints: const BoxConstraints(minWidth: 22),
                              height: 22,
                              padding: const EdgeInsets.symmetric(horizontal: 5),
                              alignment: Alignment.center,
                              decoration: const BoxDecoration(
                                color: Color(0xFFF53D3D),
                                shape: BoxShape.circle,
                              ),
                              child: Text(
                                notificationCount > 99
                                    ? '99+'
                                    : '$notificationCount',
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
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MissionSelector extends StatelessWidget {
  const _MissionSelector({
    required this.showAll,
    required this.mission,
    required this.activeCount,
    required this.onAll,
    required this.onMission,
  });

  final bool showAll;
  final DriverMissionDetail? mission;
  final int activeCount;
  final VoidCallback onAll;
  final VoidCallback onMission;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(0, 0, 0, 0),
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 11),
      decoration: const BoxDecoration(
        color: Color(0xF7FFFFFF),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(24)),
        boxShadow: [
          BoxShadow(
            color: Color(0x16000000),
            blurRadius: 10,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            flex: 10,
            child: _SelectorButton(
              selected: showAll,
              label: activeCount > 1
                  ? 'Toutes les missions ($activeCount)'
                  : 'Toutes les missions',
              onTap: onAll,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            flex: 13,
            child: _SelectorButton(
              selected: !showAll && mission != null,
              label: mission == null
                  ? 'Aucune mission active'
                  : 'Mission ${mission!.missionNumber}',
              icon: mission == null ? null : Icons.check_rounded,
              onTap: mission == null ? null : onMission,
            ),
          ),
        ],
      ),
    );
  }
}

class _SelectorButton extends StatelessWidget {
  const _SelectorButton({
    required this.selected,
    required this.label,
    required this.onTap,
    this.icon,
  });

  final bool selected;
  final String label;
  final VoidCallback? onTap;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? Colors.white : const Color(0xFFF1F8F5),
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Container(
          height: 46,
          padding: const EdgeInsets.symmetric(horizontal: 11),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            border: selected
                ? Border.all(color: OvanieColors.green, width: 1.2)
                : null,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                Container(
                  width: 27,
                  height: 27,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    color: OvanieColors.green,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, color: Colors.white, size: 18),
                ),
                const SizedBox(width: 7),
              ],
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: selected
                        ? OvanieColors.greenDark
                        : const Color(0xFF5F6986),
                    fontSize: 13.2,
                    fontWeight: FontWeight.w800,
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

class _GpsStatusCard extends StatelessWidget {
  const _GpsStatusCard({
    required this.position,
    required this.mission,
    required this.localAvailable,
  });

  final Position? position;
  final DriverMissionDetail? mission;
  final bool? localAvailable;

  @override
  Widget build(BuildContext context) {
    final unavailable = mission?.gpsStatus == 'unavailable' ||
        localAvailable == false;
    final accuracy = position?.accuracy;
    return _MapInfoCard(
      icon: unavailable ? Icons.gps_off_rounded : Icons.location_on_rounded,
      title: unavailable ? 'GPS indisponible' : 'GPS actif',
      subtitle: unavailable
          ? 'Mode manuel'
          : accuracy != null && accuracy.isFinite
              ? 'Précision ${accuracy.round()} m'
              : 'Position en cours',
      accent: unavailable ? OvanieColors.warning : OvanieColors.green,
    );
  }
}

class _NextPointCard extends StatelessWidget {
  const _NextPointCard({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    final route = mission.routePlan;
    final duration = route?.durationMinutes;
    final distance = route?.distanceKm;
    final metrics = <String>[];
    if (duration != null) metrics.add('$duration min');
    if (distance != null) metrics.add('${_compactNumber(distance)} km');

    return _MapInfoCard(
      icon: Icons.navigation_rounded,
      title: mission.nextPickupStop != null ? 'Point suivant' : 'Destination',
      subtitle: metrics.isEmpty ? 'Calcul en cours' : metrics.join(' • '),
      accent: OvanieColors.green,
      alignRight: true,
    );
  }
}

class _MapInfoCard extends StatelessWidget {
  const _MapInfoCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.accent,
    this.alignRight = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color accent;
  final bool alignRight;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 160),
      padding: const EdgeInsets.fromLTRB(10, 8, 12, 8),
      decoration: BoxDecoration(
        color: const Color(0xF8FFFFFF),
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x16000000),
            blurRadius: 10,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        textDirection: alignRight ? TextDirection.rtl : TextDirection.ltr,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: .11),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: accent, size: 23),
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Column(
              crossAxisAlignment: alignRight
                  ? CrossAxisAlignment.end
                  : CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF102044),
                    fontSize: 12.8,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF5D6B8A),
                    fontSize: 11.7,
                    fontWeight: FontWeight.w600,
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

class _MapRoundButton extends StatelessWidget {
  const _MapRoundButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xF8FFFFFF),
      shape: const CircleBorder(),
      elevation: 2,
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 48,
          height: 48,
          child: Icon(icon, color: const Color(0xFF34415F), size: 25),
        ),
      ),
    );
  }
}

class _MissionTrackingSheet extends StatelessWidget {
  const _MissionTrackingSheet({
    required this.mission,
    required this.onOpenMission,
    required this.onGpsProblem,
    required this.gpsBusy,
  });

  final DriverMissionDetail? mission;
  final VoidCallback? onOpenMission;
  final VoidCallback? onGpsProblem;
  final bool gpsBusy;

  @override
  Widget build(BuildContext context) {
    if (mission == null) {
      return Container(
        height: 142,
        padding: const EdgeInsets.fromLTRB(20, 10, 20, 18),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          boxShadow: [
            BoxShadow(
              color: Color(0x1F000000),
              blurRadius: 20,
              offset: Offset(0, -4),
            ),
          ],
        ),
        child: const Column(
          children: [
            _SheetHandle(),
            SizedBox(height: 10),
            Text(
              'Aucune mission en cours',
              style: TextStyle(
                color: Color(0xFF0E1737),
                fontSize: 20,
                fontWeight: FontWeight.w900,
              ),
            ),
            SizedBox(height: 5),
            Text(
              'Démarrez une mission pour afficher son suivi en temps réel.',
              textAlign: TextAlign.center,
              style: TextStyle(color: OvanieColors.muted, fontSize: 12.5),
            ),
          ],
        ),
      );
    }

    final nextPickup = mission!.nextPickupStop;
    final route = mission!.routePlan;
    final routeMetrics = <String>[];
    if (route?.durationMinutes != null) {
      routeMetrics.add('${route!.durationMinutes} min');
    }
    if (route?.distanceKm != null) {
      routeMetrics.add('${_compactNumber(route!.distanceKm!)} km');
    }

    final nextTitle = nextPickup != null
        ? '${nextPickup.label} — ${nextPickup.locationLabel}'
        : mission!.displayDestination;
    final nextSubtitle = nextPickup != null
        ? '${nextPickup.itemCount} article${nextPickup.itemCount > 1 ? 's' : ''} à récupérer'
        : mission!.isArrived
            ? 'Destination atteinte'
            : 'Livraison client';

    return Container(
      padding: const EdgeInsets.fromLTRB(20, 9, 20, 17),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: Color(0x20000000),
            blurRadius: 20,
            offset: Offset(0, -5),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const _SheetHandle(),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Mission ${mission!.missionNumber}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Color(0xFF0E1737),
                fontSize: 20,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: _MissionStatusPill(mission: mission!),
          ),
          const SizedBox(height: 11),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 15,
                child: _SheetMetric(
                  icon: Icons.flag_rounded,
                  label: 'Prochaine étape',
                  value: nextTitle,
                  detail: nextSubtitle,
                ),
              ),
              const _MetricDivider(),
              Expanded(
                flex: 10,
                child: _SheetMetric(
                  icon: Icons.schedule_rounded,
                  label: 'Temps estimé',
                  value: routeMetrics.isEmpty
                      ? 'Calcul en cours'
                      : routeMetrics.join(' • '),
                ),
              ),
              const _MetricDivider(),
              Expanded(
                flex: 10,
                child: _CollectionsMetric(mission: mission!),
              ),
            ],
          ),
          const SizedBox(height: 13),
          Row(
            children: [
              Expanded(
                flex: 19,
                child: SizedBox(
                  height: 50,
                  child: FilledButton(
                    onPressed: onOpenMission,
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.green,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(13),
                      ),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          'Voir la mission',
                          style: TextStyle(
                            fontSize: 15.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        SizedBox(width: 8),
                        Icon(Icons.chevron_right_rounded, size: 28),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 11,
                child: SizedBox(
                  height: 50,
                  child: OutlinedButton.icon(
                    onPressed: gpsBusy ? null : onGpsProblem,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: OvanieColors.greenDark,
                      side: const BorderSide(
                        color: OvanieColors.green,
                        width: 1.3,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(13),
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                    ),
                    icon: gpsBusy
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: OvanieColors.green,
                            ),
                          )
                        : const Icon(Icons.warning_amber_rounded, size: 22),
                    label: const Text(
                      'Problème GPS',
                      maxLines: 1,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MissionStatusPill extends StatelessWidget {
  const _MissionStatusPill({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    final (label, icon, color) = switch (mission.status) {
      'accepted' => ('Mission acceptée', Icons.check_circle_rounded, OvanieColors.green),
      'collecting' => ('Collectes en cours', Icons.inventory_2_outlined, OvanieColors.green),
      'picked_up' => ('Chargement terminé', Icons.local_shipping_rounded, OvanieColors.green),
      'in_transit' => ('En route vers le client', Icons.navigation_rounded, OvanieColors.green),
      'arrived' => ('Arrivé chez le client', Icons.location_on_rounded, OvanieColors.green),
      'incident' => ('Incident signalé', Icons.warning_amber_rounded, OvanieColors.warning),
      _ => (mission.statusLabel, Icons.route_rounded, OvanieColors.green),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: color, size: 19),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 12.5,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _SheetMetric extends StatelessWidget {
  const _SheetMetric({
    required this.icon,
    required this.label,
    required this.value,
    this.detail,
  });

  final IconData icon;
  final String label;
  final String value;
  final String? detail;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: const Color(0xFF425170), size: 22),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  color: Color(0xFF71809F),
                  fontSize: 11.3,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                value,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Color(0xFF111A39),
                  fontSize: 12.6,
                  height: 1.15,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (detail != null) ...[
                const SizedBox(height: 2),
                Text(
                  detail!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF71809F),
                    fontSize: 10.8,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _CollectionsMetric extends StatelessWidget {
  const _CollectionsMetric({required this.mission});

  final DriverMissionDetail mission;

  @override
  Widget build(BuildContext context) {
    final total = mission.pickupStops.length;
    final completed = mission.pickupCompletedCount.clamp(0, total);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.receipt_long_rounded,
            color: Color(0xFF425170), size: 22),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Collectes terminées',
                style: TextStyle(
                  color: Color(0xFF71809F),
                  fontSize: 11.3,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                total == 0 ? '—' : '$completed sur $total',
                style: const TextStyle(
                  color: Color(0xFF111A39),
                  fontSize: 12.6,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (total > 0) ...[
                const SizedBox(height: 7),
                Row(
                  children: List.generate(total, (index) {
                    return Expanded(
                      child: Container(
                        height: 6,
                        margin: EdgeInsets.only(right: index == total - 1 ? 0 : 3),
                        decoration: BoxDecoration(
                          color: index < completed
                              ? OvanieColors.green
                              : const Color(0xFFDDE7E3),
                          borderRadius: BorderRadius.circular(99),
                        ),
                      ),
                    );
                  }),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _MetricDivider extends StatelessWidget {
  const _MetricDivider();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 58,
      margin: const EdgeInsets.symmetric(horizontal: 9),
      color: const Color(0xFFE1E5ED),
    );
  }
}

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 48,
        height: 5,
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(
          color: const Color(0xFFD8DDE7),
          borderRadius: BorderRadius.circular(99),
        ),
      ),
    );
  }
}

class _AllMissionsSheet extends StatelessWidget {
  const _AllMissionsSheet({
    required this.activeMissions,
    required this.onSelect,
  });

  final List<DriverMissionSummaryModel> activeMissions;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 9, 20, 17),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: Color(0x20000000),
            blurRadius: 20,
            offset: Offset(0, -5),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const _SheetHandle(),
          Row(
            children: [
              Expanded(
                child: Text(
                  activeMissions.isEmpty
                      ? 'Aucune mission active'
                      : '${activeMissions.length} mission${activeMissions.length > 1 ? 's' : ''} active${activeMissions.length > 1 ? 's' : ''}',
                  style: const TextStyle(
                    color: Color(0xFF0E1737),
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const Icon(Icons.route_rounded, color: OvanieColors.green),
            ],
          ),
          if (activeMissions.isNotEmpty) ...[
            const SizedBox(height: 8),
            SizedBox(
              height: 58,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: activeMissions.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final mission = activeMissions[index];
                  return ActionChip(
                    avatar: const Icon(Icons.local_shipping_rounded,
                        size: 18, color: OvanieColors.green),
                    label: Text(mission.missionNumber),
                    onPressed: () => onSelect(mission.missionNumber),
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _TrackingErrorCard extends StatelessWidget {
  const _TrackingErrorCard({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 290,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(color: Color(0x22000000), blurRadius: 16),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded,
              color: OvanieColors.green, size: 34),
          const SizedBox(height: 8),
          const Text(
            'Suivi indisponible',
            style: TextStyle(
              color: Color(0xFF0F1838),
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: OvanieColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: onRetry,
            child: const Text('Réessayer'),
          ),
        ],
      ),
    );
  }
}

String _compactNumber(double value) {
  if (value == value.roundToDouble()) return value.toStringAsFixed(0);
  return value.toStringAsFixed(1).replaceAll('.', ',');
}

extension _FirstOrNullExtension<T> on Iterable<T> {
  T? get firstOrNull {
    final iterator = this.iterator;
    if (!iterator.moveNext()) return null;
    return iterator.current;
  }
}
