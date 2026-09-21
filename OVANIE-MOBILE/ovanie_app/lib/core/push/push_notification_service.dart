import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';

import '../../features/auth/domain/session_store.dart';
import '../network/api_client.dart';

class OvanieFirebaseOptions {
  OvanieFirebaseOptions._();

  static const apiKey = String.fromEnvironment('OVANIE_FIREBASE_API_KEY');
  static const appId = String.fromEnvironment('OVANIE_FIREBASE_APP_ID');
  static const messagingSenderId =
      String.fromEnvironment('OVANIE_FIREBASE_MESSAGING_SENDER_ID');
  static const projectId =
      String.fromEnvironment('OVANIE_FIREBASE_PROJECT_ID');

  static bool get configured =>
      apiKey.isNotEmpty &&
      appId.isNotEmpty &&
      messagingSenderId.isNotEmpty &&
      projectId.isNotEmpty;

  static FirebaseOptions get current => const FirebaseOptions(
        apiKey: apiKey,
        appId: appId,
        messagingSenderId: messagingSenderId,
        projectId: projectId,
      );
}

@pragma('vm:entry-point')
Future<void> ovanieFirebaseMessagingBackgroundHandler(
  RemoteMessage message,
) async {
  if (OvanieFirebaseOptions.configured) {
    await Firebase.initializeApp(options: OvanieFirebaseOptions.current);
  } else {
    await Firebase.initializeApp();
  }
}

class PushNavigationTarget {
  final String category;
  final int? orderId;
  final int? shipmentId;
  final int? returnId;
  final int? ticketId;
  final String url;

  const PushNavigationTarget({
    required this.category,
    this.orderId,
    this.shipmentId,
    this.returnId,
    this.ticketId,
    required this.url,
  });

  factory PushNavigationTarget.fromMessage(RemoteMessage message) {
    int? value(String key) {
      final parsed = int.tryParse('${message.data[key] ?? ''}');
      return parsed != null && parsed > 0 ? parsed : null;
    }

    return PushNavigationTarget(
      category: '${message.data['category'] ?? ''}'.toLowerCase(),
      orderId: value('order_id'),
      shipmentId: value('shipment_id'),
      returnId: value('return_id'),
      ticketId: value('ticket_id'),
      url: '${message.data['url'] ?? ''}',
    );
  }
}

/// Gère l'enregistrement FCM de cette installation OVANIE.
///
/// Chaque téléphone/tablette connecté au même compte conserve son propre token
/// actif. Le backend Laravel envoie ensuite les notifications métier à tous les
/// appareils actifs du compte. La déconnexion d'un appareil ne désactive que
/// son propre token.
class PushNotificationService with WidgetsBindingObserver {
  PushNotificationService._();
  static final instance = PushNotificationService._();

  final _navigation = StreamController<PushNavigationTarget>.broadcast();
  Stream<PushNavigationTarget> get navigation => _navigation.stream;

  bool _booted = false;
  bool _registering = false;
  bool _observerAttached = false;
  String? _token;
  DateTime? _lastRegistrationAt;
  StreamSubscription<String>? _tokenRefresh;
  StreamSubscription<RemoteMessage>? _opened;
  StreamSubscription<RemoteMessage>? _foreground;
  VoidCallback? _sessionListener;

  static const MethodChannel _nativeNotifications =
      MethodChannel('ovanie/notifications');

  bool get configuredInApp => OvanieFirebaseOptions.configured;
  String? get token => _token;

  Future<void> initialize() async {
    if (_booted) return;
    _booted = true;

    try {
      // Android utilise normalement google-services.json. Les dart-define
      // restent disponibles pour les builds qui fournissent leurs options.
      if (OvanieFirebaseOptions.configured) {
        await Firebase.initializeApp(options: OvanieFirebaseOptions.current);
      } else {
        await Firebase.initializeApp();
      }
      FirebaseMessaging.onBackgroundMessage(
        ovanieFirebaseMessagingBackgroundHandler,
      );

      if (!_observerAttached) {
        WidgetsBinding.instance.addObserver(this);
        _observerAttached = true;
      }

      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      _token = await messaging.getToken();

      _tokenRefresh = messaging.onTokenRefresh.listen((token) {
        _token = token;
        _lastRegistrationAt = null;
        unawaited(registerCurrentDevice(force: true));
      });

      _opened = FirebaseMessaging.onMessageOpenedApp.listen(
        (message) =>
            _navigation.add(PushNavigationTarget.fromMessage(message)),
      );

      // En premier plan, Android ne présente pas automatiquement le bandeau
      // d'un message FCM : déléguer son affichage au canal natif OVANIE.
      _foreground = FirebaseMessaging.onMessage.listen((message) {
        final notification = message.notification;
        if (notification == null) return;
        unawaited(
          _nativeNotifications.invokeMethod<void>('show', {
            'title': notification.title ?? 'OVANIE',
            'body': notification.body ?? '',
          }),
        );
      });

      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        Future<void>.delayed(
          const Duration(milliseconds: 800),
          () => _navigation.add(PushNavigationTarget.fromMessage(initial)),
        );
      }

      _sessionListener = () {
        if (SessionStore.instance.isAuthenticated) {
          _lastRegistrationAt = null;
          unawaited(registerCurrentDevice(force: true));
        }
      };
      SessionStore.instance.addListener(_sessionListener!);

      if (SessionStore.instance.isAuthenticated) {
        await registerCurrentDevice(force: true);
      }
    } catch (_) {
      // Firebase ne doit jamais empêcher l'utilisation générale d'OVANIE.
      // Un prochain retour de l'application au premier plan retentera
      // automatiquement l'enregistrement de l'appareil.
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) return;
    if (!SessionStore.instance.isAuthenticated) return;

    // Actualise le token et last_seen_at de cette installation sans désactiver
    // les autres téléphones/tablettes du même compte.
    unawaited(registerCurrentDevice(force: true));
  }

  Future<bool> serverAvailable() async {
    if (!_booted || !SessionStore.instance.isAuthenticated) return false;
    try {
      final response =
          await ApiClient.dio.get<dynamic>('/mobile/client/push');
      ApiClient.ensureSuccess(response);
      final body = response.data;
      return body is Map &&
          body['data'] is Map &&
          (body['data'] as Map)['available'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<void> registerCurrentDevice({bool force = false}) async {
    final token = _token;
    if (token == null ||
        token.isEmpty ||
        !SessionStore.instance.isAuthenticated ||
        _registering) {
      return;
    }

    final last = _lastRegistrationAt;
    if (!force &&
        last != null &&
        DateTime.now().difference(last) < const Duration(seconds: 30)) {
      return;
    }

    _registering = true;
    try {
      if (!await serverAvailable()) return;

      final platform =
          defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
      final response = await ApiClient.dio.post<dynamic>(
        '/mobile/client/push/devices',
        data: {
          'token': token,
          'platform': platform,
          'device_name': platform == 'ios'
              ? 'iPhone / iPad OVANIE'
              : 'Android OVANIE',
          'app_version': '1.0.0+92',
          'locale': PlatformDispatcher.instance.locale.toLanguageTag(),
        },
      );
      ApiClient.ensureSuccess(response);
      _lastRegistrationAt = DateTime.now();
    } catch (_) {
      // Un retour ultérieur au premier plan retentera l'enregistrement.
    } finally {
      _registering = false;
    }
  }

  Future<void> unregisterCurrentDevice() async {
    final token = _token;
    if (token == null ||
        token.isEmpty ||
        !SessionStore.instance.isAuthenticated) {
      return;
    }

    try {
      final response = await ApiClient.dio.delete<dynamic>(
        '/mobile/client/push/devices',
        data: {'token': token},
      );
      ApiClient.ensureSuccess(response);
      _lastRegistrationAt = null;
    } catch (_) {}
  }

  Future<void> dispose() async {
    if (_sessionListener != null) {
      SessionStore.instance.removeListener(_sessionListener!);
    }
    if (_observerAttached) {
      WidgetsBinding.instance.removeObserver(this);
      _observerAttached = false;
    }
    await _tokenRefresh?.cancel();
    await _opened?.cancel();
    await _foreground?.cancel();
    await _navigation.close();
  }
}
