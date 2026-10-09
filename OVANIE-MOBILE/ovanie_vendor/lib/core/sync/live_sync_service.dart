import 'dart:async';
import 'dart:math';

import 'package:flutter/widgets.dart';

import '../../data/vendor_repository.dart';
import '../../features/auth/vendor_session.dart';
import '../network/api_client.dart';

/// Synchronisation temps quasi réel avec l'espace vendeur web OVANIE.
///
/// Interroge `GET /live-sync` (versions opaques par sujet : shop = commandes,
/// produits et avis de la boutique ; notifications) tant que l'application est
/// au premier plan. Quand un sujet change sur le web ou un autre appareil, les
/// caches locaux sont invalidés et les écrans abonnés (mixin
/// [LiveSyncListener]) se rechargent seuls.
///
/// Les modifications faites par cette application portent l'en-tête
/// `X-Ovanie-Client` et ne déclenchent donc pas de rechargement inutile.
class LiveSyncService with WidgetsBindingObserver {
  LiveSyncService._();

  static final LiveSyncService instance = LiveSyncService._();

  static const String clientHeader = 'X-Ovanie-Client';
  static const Duration _minInterval = Duration(seconds: 4);
  static const Duration _maxInterval = Duration(seconds: 60);

  final String clientId = 'vendor-${_randomId()}';
  final StreamController<Set<String>> _changes =
      StreamController<Set<String>>.broadcast();
  final Map<String, int> _watched = <String, int>{};

  Map<String, String>? _baseline;
  int? _baselineUserId;
  Timer? _timer;
  bool _started = false;
  bool _foreground = true;
  bool _inFlight = false;
  Duration _interval = const Duration(seconds: 8);

  /// Sujets modifiés ailleurs (web, autre appareil, vendeur, livreur…).
  Stream<Set<String>> get changes => _changes.stream;

  void start() {
    if (_started) return;
    _started = true;
    ApiClient.dio.options.headers[clientHeader] = clientId;
    WidgetsBinding.instance.addObserver(this);
    VendorSession.instance.addListener(_sessionChanged);
    unawaited(pollNow());
  }

  /// Suit un sujet public supplémentaire (ex. `product:42`) tant que la
  /// fonction retournée n'a pas été appelée.
  VoidCallback watch(String topic) {
    _watched[topic] = (_watched[topic] ?? 0) + 1;
    unawaited(pollNow());
    return () {
      final count = (_watched[topic] ?? 1) - 1;
      if (count <= 0) {
        _watched.remove(topic);
      } else {
        _watched[topic] = count;
      }
    };
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (_foreground) {
      unawaited(pollNow());
    } else {
      _timer?.cancel();
    }
  }

  void _sessionChanged() {
    // Nouveau compte (ou déconnexion) : repartir d'une base propre.
    if (_baselineUserId != VendorSession.instance.userId) {
      _baseline = null;
      unawaited(pollNow());
    }
  }

  Future<void> pollNow() async {
    if (!_started || !_foreground || _inFlight) return;
    _timer?.cancel();
    _inFlight = true;
    try {
      final response = await ApiClient.dio.get<dynamic>(
        '/live-sync',
        queryParameters: <String, dynamic>{
          if (_watched.isNotEmpty) 'watch': _watched.keys.join(','),
        },
      );
      final data = response.data;
      if (response.statusCode != 200 || data is! Map || data['topics'] is! Map) {
        throw StateError('live-sync ${response.statusCode}');
      }

      final topics = <String, String>{
        for (final entry in (data['topics'] as Map).entries)
          '${entry.key}': '${entry.value}',
      };
      final origins = data['origins'] is Map ? data['origins'] as Map : const {};
      final pollMs = int.tryParse('${data['poll_after_ms'] ?? ''}');
      if (pollMs != null) {
        _interval = Duration(milliseconds: max(pollMs, _minInterval.inMilliseconds));
      }

      final previous = _baseline;
      final userId = VendorSession.instance.userId;
      if (previous != null && _baselineUserId == userId) {
        final changed = <String>{
          for (final entry in topics.entries)
            if (previous.containsKey(entry.key) &&
                previous[entry.key] != entry.value &&
                '${origins[entry.key] ?? ''}' != clientId)
              entry.key,
        };
        if (changed.isNotEmpty) _dispatch(changed);
      }
      _baseline = topics;
      _baselineUserId = userId;
    } catch (_) {
      _interval = Duration(
        milliseconds: min(_interval.inMilliseconds * 2, _maxInterval.inMilliseconds),
      );
    } finally {
      _inFlight = false;
      _schedule();
    }
  }

  void _schedule() {
    _timer?.cancel();
    if (!_foreground) return;
    _timer = Timer(_interval, () => unawaited(pollNow()));
  }

  void _dispatch(Set<String> changed) {
    if (changed.contains('shop')) {
      VendorRepository.instance.invalidateOrderCache();
      VendorRepository.instance.invalidateProductCache();
    }
    _changes.add(changed);
  }

  static String _randomId() {
    final random = Random.secure();
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    return List.generate(16, (_) => chars[random.nextInt(chars.length)]).join();
  }
}

/// À mélanger dans un `State` pour recharger l'écran quand un sujet change.
mixin LiveSyncListener<T extends StatefulWidget> on State<T> {
  StreamSubscription<Set<String>>? _liveSyncSubscription;
  VoidCallback? _liveSyncUnwatch;

  void listenLiveSync(
    Set<String> topics,
    VoidCallback onChanged, {
    String? watch,
  }) {
    _liveSyncSubscription?.cancel();
    _liveSyncUnwatch?.call();
    _liveSyncUnwatch = watch == null ? null : LiveSyncService.instance.watch(watch);
    final all = <String>{...topics, if (watch != null) watch};
    _liveSyncSubscription = LiveSyncService.instance.changes.listen((changed) {
      if (mounted && changed.any(all.contains)) onChanged();
    });
  }

  @override
  void dispose() {
    _liveSyncSubscription?.cancel();
    _liveSyncUnwatch?.call();
    super.dispose();
  }
}
