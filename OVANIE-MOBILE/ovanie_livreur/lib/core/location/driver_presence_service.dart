import 'dart:async';
import 'dart:math' as math;

import 'package:geolocator/geolocator.dart';

import '../../features/driver/data/driver_repository.dart';

/// Source GPS unique de l'application OVANIE Livreur.
///
/// Le même flux stabilisé sert à :
/// - afficher le véhicule dans l'onglet Suivi ;
/// - maintenir le livreur en ligne côté Logistique ;
/// - alimenter les positions des missions.
///
/// Cela évite d'avoir deux GPS concurrents (ancien écran Suivi + présence) qui
/// pouvaient afficher/enregistrer des coordonnées différentes.
class DriverPresenceService {
  DriverPresenceService._();

  static final DriverPresenceService instance = DriverPresenceService._();

  final StreamController<Position> _stablePositions =
      StreamController<Position>.broadcast();

  Timer? _timer;
  StreamSubscription<Position>? _rawSubscription;
  Future<void>? _inFlight;
  bool _started = false;
  bool _gpsAvailable = false;

  Position? _stablePosition;
  Position? _motionCandidate;
  DateTime? _motionCandidateSince;
  int _motionConfirmations = 0;
  int _stopConfirmations = 0;
  bool _motionActive = false;

  Stream<Position> get positions => _stablePositions.stream;
  Position? get stablePosition => _stablePosition;
  bool get gpsAvailable => _gpsAvailable;

  Future<void> start() async {
    if (_started) {
      await refreshNow();
      return;
    }

    _started = true;
    await _ensureLocationStream();
    await refreshNow();

    _timer?.cancel();
    _timer = Timer.periodic(
      const Duration(seconds: 45),
      (_) => _sendOnce(),
    );
  }

  Future<void> refreshNow() => _sendOnce(force: true);

  Future<void> stop({bool notifyBackend = false}) async {
    _started = false;
    _timer?.cancel();
    _timer = null;
    await _rawSubscription?.cancel();
    _rawSubscription = null;

    final running = _inFlight;
    if (running != null) {
      try {
        await running;
      } catch (_) {}
    }

    if (notifyBackend) {
      try {
        await DriverRepository.instance.setOffline();
      } catch (_) {
        // Le backend expirera automatiquement la présence si le réseau est coupé.
      }
    }
  }

  Future<void> _ensureLocationStream() async {
    if (_rawSubscription != null) return;

    if (!await Geolocator.isLocationServiceEnabled()) {
      _gpsAvailable = false;
      return;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      _gpsAvailable = false;
      return;
    }

    _gpsAvailable = true;
    _rawSubscription = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.bestForNavigation,
        // Ne pas déléguer l'anti-dérive à Android : on garde les lectures
        // brutes et on décide nous-mêmes quand le véhicule a réellement bougé.
        distanceFilter: 0,
      ),
    ).listen(
      _consumeRawPosition,
      onError: (_) {},
    );
  }

  void _consumeRawPosition(Position raw) {
    final stable = _stabilize(raw);
    if (stable == null) return;

    _stablePosition = stable;
    if (!_stablePositions.isClosed) {
      _stablePositions.add(stable);
    }
  }

  /// Stabilisation anti-dérive GPS : filtre les sauts dus au bruit du signal
  /// sans exiger une vitesse de véhicule (un livreur à pied ou qui démarre
  /// doucement doit aussi faire avancer le marqueur).
  ///
  /// À l'arrêt, les coordonnées restent VERROUILLÉES. Une nouvelle position ne
  /// déplace le véhicule que lorsqu'un mouvement est confirmé par plusieurs
  /// lectures successives avec une vitesse cohérente. Un simple saut GPS de
  /// 20, 40 ou 80 m ne peut donc plus faire "avancer" le véhicule tout seul.
  Position? _stabilize(Position candidate) {
    if (!candidate.latitude.isFinite || !candidate.longitude.isFinite) {
      return null;
    }

    final accuracy = candidate.accuracy.isFinite
        ? candidate.accuracy.clamp(0.0, 9999.0).toDouble()
        : 9999.0;
    if (accuracy > 30.0) return null;

    final anchor = _stablePosition;
    if (anchor == null) {
      _resetMotionCandidate();
      _motionActive = false;
      return candidate;
    }

    final distance = Geolocator.distanceBetween(
      anchor.latitude,
      anchor.longitude,
      candidate.latitude,
      candidate.longitude,
    );
    if (!distance.isFinite) return null;

    final speed = candidate.speed.isFinite && candidate.speed > 0
        ? candidate.speed
        : 0.0;

    // Zone morte large volontaire : avec une précision annoncée à 20 m, un
    // téléphone immobile peut osciller bien au-delà de 20 m en milieu urbain.
    final deadZone = math.max(35.0, accuracy * 2.25)
        .clamp(35.0, 85.0)
        .toDouble();

    if (!_motionActive) {
      if (distance <= deadZone) {
        _resetMotionCandidate();
        return null;
      }

      // Une position lointaine sans vitesse cohérente reste considérée comme
      // une dérive. On ne "relocalise" jamais le marqueur uniquement parce que
      // plusieurs points GPS erronés se sont déplacés dans la même direction.
      // Seuil abaissé à la marche (0,5 m/s) : à 2,2 m/s (~8 km/h), un livreur
      // qui teste à pied ou qui démarre doucement ne faisait jamais bouger le
      // marqueur ni dans l'app ni côté logistique (même source de données).
      // La zone morte de distance et les 4 confirmations restent le vrai
      // filtre anti-dérive GPS.
      if (speed < 0.5) {
        _resetMotionCandidate();
        return null;
      }

      final now = DateTime.now();
      final previousCandidate = _motionCandidate;
      if (previousCandidate == null) {
        _motionCandidate = candidate;
        _motionCandidateSince = now;
        _motionConfirmations = 1;
        return null;
      }

      final candidateStep = Geolocator.distanceBetween(
        previousCandidate.latitude,
        previousCandidate.longitude,
        candidate.latitude,
        candidate.longitude,
      );

      // Les trois lectures doivent former un mouvement continu. Un nouveau saut
      // incohérent recommence la confirmation depuis zéro.
      if (!candidateStep.isFinite ||
          candidateStep < 1.5 ||
          candidateStep > 90.0 ||
          speed < 0.5) {
        _motionCandidate = candidate;
        _motionCandidateSince = now;
        _motionConfirmations = 1;
        return null;
      }

      _motionCandidate = candidate;
      _motionConfirmations += 1;
      final elapsed = _motionCandidateSince == null
          ? Duration.zero
          : now.difference(_motionCandidateSince!);

      if (_motionConfirmations < 4 ||
          elapsed < const Duration(seconds: 4)) {
        return null;
      }

      _motionActive = true;
      _stopConfirmations = 0;
      _resetMotionCandidate();
      return candidate;
    }

    // Déjà en mouvement : on reste réactif, mais on n'accepte pas de micro
    // oscillations sous la précision GPS.
    if (speed < 0.8) {
      _stopConfirmations += 1;
      if (_stopConfirmations >= 3) {
        _motionActive = false;
        _stopConfirmations = 0;
      }
      return null;
    }

    _stopConfirmations = 0;
    final movingMinDistance = math.max(5.0, accuracy * 0.45)
        .clamp(5.0, 20.0)
        .toDouble();
    if (distance < movingMinDistance) return null;

    return candidate;
  }

  void _resetMotionCandidate() {
    _motionCandidate = null;
    _motionCandidateSince = null;
    _motionConfirmations = 0;
  }

  Future<void> _sendOnce({bool force = false}) async {
    if (!_started && !force) return;

    final existing = _inFlight;
    if (existing != null) {
      await existing;
      return;
    }

    final future = _performHeartbeat();
    _inFlight = future;
    try {
      await future;
    } finally {
      if (identical(_inFlight, future)) {
        _inFlight = null;
      }
    }
  }

  Future<void> _performHeartbeat() async {
    try {
      await _ensureLocationStream();
      if (!_gpsAvailable) return;

      // Au premier démarrage seulement, amorcer le verrou GPS. Ensuite on ne
      // demande plus une seconde position indépendante : toute l'application
      // partage le même point stabilisé.
      if (_stablePosition == null) {
        final raw = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.bestForNavigation,
            timeLimit: Duration(seconds: 15),
          ),
        );
        _consumeRawPosition(raw);
      }

      final position = _stablePosition;
      if (position == null) return;

      await DriverRepository.instance.sendPresence(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
        // À l'arrêt, envoyer explicitement 0 évite que le backend interprète
        // une ancienne vitesse parasite comme un déplacement.
        speed: _motionActive && position.speed.isFinite
            ? math.max(0.0, position.speed)
            : 0.0,
        heading: _motionActive && position.heading.isFinite
            ? position.heading
            : null,
      );
    } catch (_) {
      // Le prochain heartbeat réessaiera. Le backend passe automatiquement le
      // livreur hors ligne si aucune position fraîche n'arrive.
    }
  }
}
