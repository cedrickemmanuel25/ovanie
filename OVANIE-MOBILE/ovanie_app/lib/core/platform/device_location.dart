import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';

class DevicePosition {
  final double latitude;
  final double longitude;
  final double? accuracy;
  final String? provider;
  final DateTime? timestamp;
  final bool isMocked;
  final bool isEmulator;

  const DevicePosition({
    required this.latitude,
    required this.longitude,
    this.accuracy,
    this.provider,
    this.timestamp,
    this.isMocked = false,
    this.isEmulator = false,
  });

  bool get hasGoodDeliveryAccuracy =>
      accuracy != null &&
      accuracy!.isFinite &&
      accuracy! > 0 &&
      accuracy! <= DeviceLocation.maxAcceptedAccuracyMeters;
}

class NativeResolvedAddress {
  final String displayName;
  final String featureName;
  final String thoroughfare;
  final String subThoroughfare;
  final String subLocality;
  final String locality;
  final String subAdminArea;
  final String adminArea;
  final String countryName;
  final String postalCode;

  const NativeResolvedAddress({
    required this.displayName,
    required this.featureName,
    required this.thoroughfare,
    required this.subThoroughfare,
    required this.subLocality,
    required this.locality,
    required this.subAdminArea,
    required this.adminArea,
    required this.countryName,
    required this.postalCode,
  });

  factory NativeResolvedAddress.fromMap(Map<String, dynamic> raw) {
    String text(dynamic value) => (value ?? '').toString().trim();

    return NativeResolvedAddress(
      displayName: text(raw['displayName']),
      featureName: text(raw['featureName']),
      thoroughfare: text(raw['thoroughfare']),
      subThoroughfare: text(raw['subThoroughfare']),
      subLocality: text(raw['subLocality']),
      locality: text(raw['locality']),
      subAdminArea: text(raw['subAdminArea']),
      adminArea: text(raw['adminArea']),
      countryName: text(raw['countryName']),
      postalCode: text(raw['postalCode']),
    );
  }
}

/// Géolocalisation OVANIE V70 — acquisition Android robuste, sans carte.
///
/// Principes :
/// - vrai téléphone : lancer plusieurs sources de position FRAÎCHE en parallèle
///   (Geolocator/Fused + flux de mise à jour + secours Android natif) ;
/// - ne jamais attendre 30 secondes en série lorsque le GPS est difficile ;
/// - accepter un dernier point uniquement s'il est TRÈS récent et suffisamment
///   précis, puis continuer à améliorer la mesure ;
/// - émulateur : le point Android Studio > Extended Controls > Location >
///   Set Location reste la source de vérité ;
/// - l'adresse est un libellé ; les coordonnées du GPS ne sont jamais remplacées
///   par les coordonnées d'un géocodeur.
class DeviceLocation {
  DeviceLocation._();

  static const MethodChannel _nativeChannel = MethodChannel('ovanie/location');
  static const Duration _permissionTimeout = Duration(seconds: 5);
  static const Duration _singleFixTimeout = Duration(seconds: 12);
  static const Duration _streamFirstFixTimeout = Duration(seconds: 8);

  /// V60 : une adresse automatique issue de « Ma position » n'est utilisée
  /// que lorsque l'incertitude est <= 15 m. Au-dessus, les coordonnées servent
  /// uniquement à afficher la convergence du GPS ; aucun quartier/rue n'est
  /// confirmé automatiquement.
  static const double maxAcceptedAccuracyMeters = 30;

  /// Le téléphone peut démarrer avec une mesure réseau très large. On la garde
  /// uniquement comme diagnostic pendant que le GNSS converge.
  static const double maxDisplayAccuracyMeters = 100000;

  /// V65 : seuil utilisé seulement pour démarrer rapidement l'identification
  /// d'une adresse. Ce n'est pas un seuil de GPS « exact ».
  static const double addressLookupAccuracyMeters = 500;

  /// Objectif opérationnel : <= 5 m. <= 2 m est idéal, mais dépend du matériel
  /// GNSS et de l'environnement et ne peut pas être garanti par le logiciel.
  static const double immediateAccuracyMeters = 5;
  static const double idealAccuracyMeters = 2;

  /// Ouvre les paramètres uniquement après une action explicite de l'utilisateur.
  /// OVANIE ne redirige jamais automatiquement vers la fiche Android de l'app.
  static Future<bool> openAppLocationSettings() async {
    try {
      return await Geolocator.openAppSettings();
    } catch (_) {
      return false;
    }
  }

  static Future<bool> openDeviceLocationSettings() async {
    try {
      return await Geolocator.openLocationSettings();
    } catch (_) {
      return false;
    }
  }

  static Future<DevicePosition> currentPosition() async {
    await _ensurePermission();

    final isAndroid = defaultTargetPlatform == TargetPlatform.android;
    final emulator = isAndroid && await _isAndroidEmulator();

    if (emulator) {
      final position = await _emulatorPosition();
      if (position == null) {
        throw const DeviceLocationException(
          'Aucun point GPS de test n’est défini. Dans Android Studio > Location, cliquez sur Set Location, ou utilisez le script Android Studio > Extended Controls > Location.',
        );
      }
      final simulated = _markAsEmulator(position);
      _debugPosition('emulator-selected', simulated);
      return simulated;
    }

    if (isAndroid && !await _hasAndroidFineLocationPermission()) {
      throw const DeviceLocationException(
        'OVANIE reçoit seulement une position approximative. Dans Paramètres > Applications > OVANIE > Autorisations > Localisation, activez « Position précise », puis réessayez.',
      );
    }

    // V53 : les sources sont démarrées EN PARALLÈLE. On retourne dès qu'un
    // fix <= 50 m arrive ; sinon on attend brièvement les autres sources et on
    // choisit la meilleure. Cela évite d'attendre inutilement 15–20 secondes
    // alors que le fournisseur Fused possède déjà une position correcte.
    DevicePosition? selected = await _bestFreshPosition(isAndroid: isAndroid);

    // V54 : un dernier point connu n'est utilisé qu'en secours STRICT, s'il est
    // très récent et déjà précis. Android documente getLastKnownLocation comme
    // un cache ; on ne l'accepte donc jamais sans contrôler âge + précision.
    // Cela évite le faux ancien quartier tout en permettant aux vrais téléphones
    // de fonctionner lorsque Fused a calculé un bon point juste avant le clic.
    if (selected == null || _accuracyOf(selected) > maxAcceptedAccuracyMeters) {
      final recent = await _recentAccurateLastKnownPosition();
      if (recent != null) {
        selected = selected == null ? recent : _betterPosition(selected, recent);
      }
    }

    if (selected == null) {
      throw const DeviceLocationException(
        'Localisation indisponible. Vérifiez que la localisation précise est activée puis réessayez.',
      );
    }

    _debugPosition('initial-best', selected);

    // V63 : attendre brièvement les sources Fused haute précision en parallèle, sans
    // basculer vers une carte. On retourne ensuite le meilleur point réellement reçu,
    // et positionStream continue
    // à l’améliorer en direct tant que le checkout reste ouvert.

    if (selected.isMocked) {
      throw const DeviceLocationException(
        'Une position GPS simulée a été détectée. Désactivez l’application de fausse localisation et réessayez.',
      );
    }

    // Même une première mesure très large est renvoyée comme diagnostic afin
    // que le checkout puisse démarrer le flux Fused continu. Elle ne sera jamais
    // reverse-géocodée ni enregistrée tant que la précision n'atteint pas le seuil
    // de livraison. C'est important après un cold start : Android peut commencer
    // par le réseau puis converger vers GNSS quelques secondes plus tard.
    return selected;
  }

  /// Suivi en direct du checkout.
  static Stream<DevicePosition> positionStream({
    int distanceFilterMeters = 8,
  }) async* {
    await _ensurePermission();

    final isAndroid = defaultTargetPlatform == TargetPlatform.android;
    if (isAndroid && await _isAndroidEmulator()) {
      DevicePosition? previous;
      while (true) {
        final snapshot = await _nativeGpsSnapshot();
        // Dans un AVD, getLastKnownLocation("gps") représente le point
        // actuellement injecté par Android Studio. Son timestamp peut rester
        // ancien tant que le point ne bouge pas : ce n’est pas une raison pour
        // considérer le point de test comme invalide.
        final current = snapshot != null ? _markAsEmulator(snapshot) : null;
        if (current != null) {
          final changed = previous == null ||
              _distanceMeters(
                    previous.latitude,
                    previous.longitude,
                    current.latitude,
                    current.longitude,
                  ) >=
                  distanceFilterMeters;
          if (changed) {
            previous = current;
            _debugPosition('emulator-live', current);
            yield current;
          }
        }
        await Future<void>.delayed(const Duration(seconds: 1));
      }
    }

    if (isAndroid && !await _hasAndroidFineLocationPermission()) {
      throw const DeviceLocationException(
        'La position précise est désactivée pour OVANIE.',
      );
    }

    final LocationSettings settings = isAndroid
        ? AndroidSettings(
            accuracy: LocationAccuracy.bestForNavigation,
            distanceFilter: distanceFilterMeters,
            // V61 : conserver FusedLocationProviderClient comme source principale.
            // Il fusionne GNSS, Wi-Fi, réseau mobile et capteurs. LocationManager
            // reste uniquement un secours dédié plus bas.
            forceLocationManager: false,
            intervalDuration: const Duration(seconds: 1),
          )
        : LocationSettings(
            accuracy: LocationAccuracy.bestForNavigation,
            distanceFilter: distanceFilterMeters,
          );

    await for (final position
        in Geolocator.getPositionStream(locationSettings: settings)) {
      if (!_isUsable(position) || position.isMocked) continue;
      final converted = _fromGeolocator(position, provider: 'geolocator_live');
      if (!_isRecent(converted, const Duration(minutes: 2))) continue;
      if (_accuracyOf(converted) > maxDisplayAccuracyMeters) continue;
      _debugPosition('device-live', converted);
      yield converted;
    }
  }

  /// Reverse-geocoding Android de secours. Il ne modifie jamais le point GPS.
  static Future<NativeResolvedAddress?> reverseGeocodeNative({
    required double latitude,
    required double longitude,
  }) async {
    if (defaultTargetPlatform != TargetPlatform.android) return null;
    if (!_validCoordinates(latitude, longitude)) return null;

    try {
      final raw = await _nativeChannel
          .invokeMapMethod<String, dynamic>('reverseGeocode', {
            'latitude': latitude,
            'longitude': longitude,
          })
          .timeout(const Duration(seconds: 12));
      if (raw == null || raw.isEmpty) return null;
      final result = NativeResolvedAddress.fromMap(raw);
      return result.displayName.isEmpty &&
              result.locality.isEmpty &&
              result.subLocality.isEmpty &&
              result.thoroughfare.isEmpty
          ? null
          : result;
    } catch (_) {
      return null;
    }
  }

  static Future<void> _ensurePermission() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled().timeout(
      _permissionTimeout,
      onTimeout: () => true,
    );
    if (!serviceEnabled) {
      throw const DeviceLocationException(
        'Activez la localisation du téléphone puis réessayez.',
        reason: DeviceLocationFailureReason.serviceDisabled,
      );
    }

    var permission = await Geolocator.checkPermission().timeout(
      _permissionTimeout,
      onTimeout: () => LocationPermission.denied,
    );

    if (permission == LocationPermission.denied) {
      // Ne jamais minuter la boîte de dialogue Android : l'utilisateur doit
      // pouvoir lire et choisir tranquillement. Un timeout transformait à tort
      // une demande encore ouverte en refus et envoyait vers la fiche de l'app.
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      throw const DeviceLocationException(
        'Localisation refusée. Autorisez la position pour utiliser « Ma position ».',
        reason: DeviceLocationFailureReason.permissionDenied,
      );
    }

    if (permission == LocationPermission.deniedForever) {
      throw const DeviceLocationException(
        'L’autorisation de localisation est désactivée pour OVANIE. Ouvrez les autorisations de l’application pour l’activer, puis réessayez.',
        reason: DeviceLocationFailureReason.permissionDeniedForever,
      );
    }
  }

  static Future<bool> _hasAndroidFineLocationPermission() async {
    try {
      final value = await _nativeChannel
          .invokeMethod<bool>('hasFineLocationPermission')
          .timeout(const Duration(seconds: 2));
      return value == true;
    } catch (_) {
      return true;
    }
  }

  static Future<bool> _isAndroidEmulator() async {
    try {
      final value = await _nativeChannel
          .invokeMethod<bool>('isEmulator')
          .timeout(const Duration(seconds: 2));
      return value == true;
    } catch (_) {
      return false;
    }
  }

  static Future<DevicePosition?> _emulatorPosition() async {
    // Android Emulator expose le dernier point injecté comme position GPS
    // courante. Contrairement à un téléphone réel, son timestamp n'est pas une
    // preuve de fraîcheur : si le point reste immobile plusieurs minutes, il
    // reste néanmoins le point choisi dans Extended Controls > Location.
    var snapshot = await _nativeGpsSnapshot();
    if (snapshot != null &&
        _validCoordinates(snapshot.latitude, snapshot.longitude)) {
      return snapshot;
    }

    // Si aucun snapshot n'existe encore, la couche Android attend brièvement un
    // nouveau fix. Cela permet de cliquer sur « Ma position » puis d'injecter un
    // point sans devoir redémarrer l'application.
    final fresh = await _nativeFreshPosition();
    if (fresh != null && _validCoordinates(fresh.latitude, fresh.longitude)) {
      return fresh;
    }

    await Future<void>.delayed(const Duration(milliseconds: 500));
    snapshot = await _nativeGpsSnapshot();
    if (snapshot != null &&
        _validCoordinates(snapshot.latitude, snapshot.longitude)) {
      return snapshot;
    }
    return null;
  }

  static Future<DevicePosition?> _nativeGpsSnapshot() async {
    try {
      final raw = await _nativeChannel
          .invokeMapMethod<String, dynamic>('getGpsSnapshot')
          .timeout(const Duration(seconds: 3));
      return _fromNativeMap(raw, fallbackProvider: 'android_gps_snapshot');
    } catch (_) {
      return null;
    }
  }

  static Future<DevicePosition?> _nativeFreshPosition() async {
    try {
      final raw = await _nativeChannel
          .invokeMapMethod<String, dynamic>('getCurrentPosition')
          .timeout(_singleFixTimeout);
      final result = _fromNativeMap(raw, fallbackProvider: 'android_fresh');
      if (result == null) return null;
      if (!_isRecent(result, const Duration(minutes: 1))) return null;
      return result;
    } catch (_) {
      return null;
    }
  }

  // V66 : pas de second SDK Google Play services dans MainActivity.
  // Geolocator utilise déjà FusedLocationProviderClient sur Android lorsque
  // forceLocationManager=false. Cela évite les dépendances Kotlin dupliquées.

  static Future<DevicePosition?> _recentAccurateLastKnownPosition() async {
    try {
      final position = await Geolocator.getLastKnownPosition()
          .timeout(const Duration(seconds: 3));
      if (position == null || !_isUsable(position)) return null;

      final candidate = _fromGeolocator(
        position,
        provider: 'geolocator_recent_cache',
      );

      if (!_isRecent(candidate, const Duration(seconds: 60))) return null;
      if (_accuracyOf(candidate) > addressLookupAccuracyMeters) return null;
      _debugPosition('recent-address-assist', candidate);
      return candidate;
    } catch (_) {
      return null;
    }
  }

  static Future<DevicePosition?> _freshGeolocatorPosition() async {
    try {
      final isAndroid = defaultTargetPlatform == TargetPlatform.android;
      final LocationSettings settings = isAndroid
          ? AndroidSettings(
              // V58 : sur téléphone physique on demande explicitement la
              // précision navigation afin de déclencher le GNSS. Les positions
              // réseau grossières restent visibles comme diagnostic mais ne
              // sont jamais transformées en adresse de livraison.
              accuracy: LocationAccuracy.bestForNavigation,
              distanceFilter: 0,
              forceLocationManager: false,
              intervalDuration: const Duration(seconds: 1),
              timeLimit: const Duration(seconds: 8),
            )
          : const LocationSettings(
              accuracy: LocationAccuracy.bestForNavigation,
              distanceFilter: 0,
              timeLimit: Duration(seconds: 8),
            );

      final position = await Geolocator.getCurrentPosition(
        locationSettings: settings,
      ).timeout(_singleFixTimeout);

      if (!_isUsable(position)) return null;
      final converted = _fromGeolocator(position, provider: 'geolocator_current');
      if (!_isRecent(converted, const Duration(minutes: 1))) return null;
      return converted;
    } on PermissionDeniedException {
      rethrow;
    } on LocationServiceDisabledException {
      rethrow;
    } catch (_) {
      return null;
    }
  }

  static Future<DevicePosition?> _firstFreshStreamPosition() async {
    StreamSubscription<Position>? subscription;
    final completer = Completer<DevicePosition?>();
    Timer? timer;
    DevicePosition? best;

    try {
      final isAndroid = defaultTargetPlatform == TargetPlatform.android;
      final LocationSettings settings = isAndroid
          ? AndroidSettings(
              accuracy: LocationAccuracy.bestForNavigation,
              distanceFilter: 0,
              forceLocationManager: false,
              intervalDuration: const Duration(seconds: 1),
            )
          : const LocationSettings(
              accuracy: LocationAccuracy.bestForNavigation,
              distanceFilter: 0,
            );

      subscription = Geolocator.getPositionStream(locationSettings: settings).listen(
        (position) {
          if (!_isUsable(position)) return;
          final candidate = _fromGeolocator(position, provider: 'geolocator_stream');
          if (!_isRecent(candidate, const Duration(minutes: 1))) return;
          best = best == null ? candidate : _betterPosition(best!, candidate);

          if (_accuracyOf(candidate) <= maxAcceptedAccuracyMeters &&
              !completer.isCompleted) {
            completer.complete(candidate);
          }
        },
        onError: (_) {
          if (!completer.isCompleted) completer.complete(best);
        },
      );

      timer = Timer(_streamFirstFixTimeout, () {
        if (!completer.isCompleted) completer.complete(best);
      });

      return await completer.future;
    } catch (_) {
      return best;
    } finally {
      timer?.cancel();
      await subscription?.cancel();
    }
  }

  static Future<DevicePosition?> _bestFreshPosition({required bool isAndroid}) async {
    final completer = Completer<DevicePosition?>();
    DevicePosition? best;
    Timer? acceptableGraceTimer;
    Timer? addressAssistGraceTimer;

    // Android V66 : Geolocator/Fused + flux Fused
    // + cache système récent. On n'utilise plus NETWORK_PROVIDER/LocationManager
    // dans la sélection normale d'un vrai téléphone : ils pouvaient gagner le
    // timeout avec un point de 100-500 m avant le fix GNSS/Fused précis.
    final futures = <Future<DevicePosition?>>[
      // V70 : on conserve les deux sources Fused actives, puis on ajoute deux
      // secours qui avaient permis au vrai téléphone de fonctionner avant V69.
      // Le cache est accepté seulement s'il date de moins d'une minute et reste
      // suffisamment proche pour identifier une adresse. Le flux actif continue
      // ensuite à remplacer ce point dès qu'Android fournit mieux.
      _freshGeolocatorPosition(),
      _firstFreshStreamPosition(),
      _recentAccurateLastKnownPosition(),
      if (isAndroid) _nativeFreshPosition(),
    ];
    var pending = futures.length;

    void accept(DevicePosition? candidate) {
      if (candidate != null &&
          _validCoordinates(candidate.latitude, candidate.longitude) &&
          _isRecent(candidate, const Duration(minutes: 1))) {
        best = best == null ? candidate : _betterPosition(best!, candidate);

        // <= 5 m : il est inutile d'attendre davantage. Entre 5 et 15 m, on
        // laisse les autres sources terminer pour avoir une chance d'obtenir un
        // fix encore meilleur avant d'identifier l'adresse.
        final bestAccuracy = _accuracyOf(best!);
        if (bestAccuracy <= immediateAccuracyMeters && !completer.isCompleted) {
          acceptableGraceTimer?.cancel();
          addressAssistGraceTimer?.cancel();
          completer.complete(best);
          return;
        }

        // Un fix <=15 m est déjà très bon : on laisse une courte chance au
        // fournisseur Fused de faire encore mieux.
        if (bestAccuracy <= maxAcceptedAccuracyMeters &&
            acceptableGraceTimer == null &&
            !completer.isCompleted) {
          addressAssistGraceTimer?.cancel();
          acceptableGraceTimer = Timer(const Duration(milliseconds: 700), () {
            if (!completer.isCompleted) completer.complete(best);
          });
        } else if (bestAccuracy <= addressLookupAccuracyMeters &&
            addressAssistGraceTimer == null &&
            acceptableGraceTimer == null &&
            !completer.isCompleted) {
          // Pour l'UX du checkout, ne pas bloquer 20-30 s sur une première
          // position à 30-500 m. Après une courte fenêtre, on la retourne pour
          // identifier l'adresse ; positionStream continue ensuite à améliorer
          // les coordonnées sans bloquer le checkout.
          addressAssistGraceTimer = Timer(const Duration(milliseconds: 1200), () {
            if (!completer.isCompleted) completer.complete(best);
          });
        }
      }

      pending -= 1;
      if (pending <= 0 && !completer.isCompleted) {
        completer.complete(best);
      }
    }

    Future<void> run(Future<DevicePosition?> future) async {
      try {
        accept(await future);
      } catch (_) {
        accept(null);
      }
    }

    for (final future in futures) {
      unawaited(run(future));
    }

    final timer = Timer(const Duration(seconds: 12), () {
      if (!completer.isCompleted) completer.complete(best);
    });

    try {
      return await completer.future;
    } finally {
      timer.cancel();
      acceptableGraceTimer?.cancel();
      addressAssistGraceTimer?.cancel();
    }
  }


  // V65 : helpers restaurés. Ils avaient été supprimés accidentellement dans
  // V63/V64 alors que plusieurs chemins d'acquisition les utilisent encore.
  static DevicePosition _betterPosition(
    DevicePosition first,
    DevicePosition? second,
  ) {
    if (second == null) return first;

    final firstAccuracy = _accuracyOf(first);
    final secondAccuracy = _accuracyOf(second);

    if (secondAccuracy + 5 < firstAccuracy) return second;
    if (firstAccuracy + 5 < secondAccuracy) return first;

    final epoch = DateTime.fromMillisecondsSinceEpoch(0);
    final firstTime = first.timestamp ?? epoch;
    final secondTime = second.timestamp ?? epoch;
    return secondTime.isAfter(firstTime) ? second : first;
  }

  static DevicePosition? _fromNativeMap(
    Map<String, dynamic>? raw, {
    required String fallbackProvider,
  }) {
    if (raw == null || raw.isEmpty) return null;
    final lat = _asDouble(raw['latitude']);
    final lng = _asDouble(raw['longitude']);
    if (lat == null || lng == null || !_validCoordinates(lat, lng)) return null;

    return DevicePosition(
      latitude: lat,
      longitude: lng,
      accuracy: _asDouble(raw['accuracy']),
      provider: (raw['provider']?.toString().trim().isNotEmpty ?? false)
          ? raw['provider'].toString()
          : fallbackProvider,
      timestamp: _timestampFromMillis(raw['timestampMillis']),
      isMocked: raw['isMocked'] == true,
      isEmulator: raw['isEmulator'] == true,
    );
  }

  static bool _isUsable(Position? position) {
    if (position == null) return false;
    return _validCoordinates(position.latitude, position.longitude);
  }

  static bool _validCoordinates(double lat, double lng) {
    if (!lat.isFinite || !lng.isFinite) return false;
    if (lat == 0 && lng == 0) return false;
    return lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
  }

  static bool _isRecent(DevicePosition position, Duration maxAge) {
    final timestamp = position.timestamp;
    if (timestamp == null) return true;
    final age = DateTime.now().difference(timestamp);
    return age.isNegative || age <= maxAge;
  }

  static DevicePosition _markAsEmulator(DevicePosition position) {
    return DevicePosition(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      provider: 'android_emulator_simulated',
      timestamp: position.timestamp,
      isMocked: position.isMocked,
      isEmulator: true,
    );
  }

  static double _accuracyOf(DevicePosition position) {
    final value = position.accuracy;
    if (value == null || !value.isFinite || value <= 0) return 9999;
    return value;
  }

  static DevicePosition _fromGeolocator(
    Position position, {
    String? provider,
  }) {
    return DevicePosition(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      provider: provider,
      timestamp: position.timestamp,
      isMocked: position.isMocked,
    );
  }

  static DateTime? _timestampFromMillis(dynamic value) {
    final millis = value is num ? value.toInt() : int.tryParse('${value ?? ''}');
    if (millis == null || millis <= 0) return null;
    return DateTime.fromMillisecondsSinceEpoch(millis);
  }

  static double? _asDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse('${value ?? ''}');
  }

  static double _distanceMeters(
    double lat1,
    double lng1,
    double lat2,
    double lng2,
  ) {
    const earthRadius = 6371000.0;
    double toRad(double value) => value * math.pi / 180.0;
    final dLat = toRad(lat2 - lat1);
    final dLng = toRad(lng2 - lng1);
    final a = math.sin(dLat / 2) * math.sin(dLat / 2) +
        math.cos(toRad(lat1)) *
            math.cos(toRad(lat2)) *
            math.sin(dLng / 2) *
            math.sin(dLng / 2);
    return 2 * earthRadius * math.atan2(math.sqrt(a), math.sqrt(1 - a));
  }

  static void _debugPosition(String label, DevicePosition position) {
    if (!kDebugMode) return;
    debugPrint(
      '[OVANIE GEO][$label] provider=${position.provider} '
      'lat=${position.latitude.toStringAsFixed(7)} '
      'lng=${position.longitude.toStringAsFixed(7)} '
      'accuracy=${position.accuracy} mocked=${position.isMocked} emulator=${position.isEmulator} '
      'timestamp=${position.timestamp}',
    );
  }
}

enum DeviceLocationFailureReason {
  other,
  serviceDisabled,
  permissionDenied,
  permissionDeniedForever,
}

class DeviceLocationException implements Exception {
  final String message;
  final DeviceLocationFailureReason reason;

  const DeviceLocationException(
    this.message, {
    this.reason = DeviceLocationFailureReason.other,
  });

  @override
  String toString() => message;
}
