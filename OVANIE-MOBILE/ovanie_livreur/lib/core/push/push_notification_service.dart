import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../network/api_client.dart';
import '../storage/token_storage.dart';

/// Options Firebase de l'app OVANIE Livreur, fournies au moment du build via
/// `--dart-define` (aucun fichier google-services.json/GoogleService-Info.plist
/// n'est nécessaire pour ce mode d'initialisation). Même mécanisme que
/// `OvanieFirebaseOptions` côté app client (ovanie_app) : `apiKey`,
/// `messagingSenderId` et `projectId` sont partagés avec le même projet
/// Firebase OVANIE ; seul `appId` est propre à cette application.
class OvanieDriverFirebaseOptions {
  OvanieDriverFirebaseOptions._();

  static const apiKey = String.fromEnvironment('OVANIE_FIREBASE_API_KEY');
  static const appId = String.fromEnvironment('OVANIE_DRIVER_FIREBASE_APP_ID');
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
Future<void> ovanieDriverFirebaseMessagingBackgroundHandler(
  RemoteMessage message,
) async {
  // Enregistrer ce handler suffit à ce qu'Android/iOS affiche la
  // notification système à partir du payload `notification` FCM, même
  // application fermée. Aucun traitement supplémentaire n'est nécessaire :
  // au retour au premier plan, l'écran des missions se recharge et affiche
  // la nouvelle course.
  if (OvanieDriverFirebaseOptions.configured) {
    await Firebase.initializeApp(options: OvanieDriverFirebaseOptions.current);
  } else {
    await Firebase.initializeApp();
  }
}

/// Gère l'enregistrement FCM de cette installation de l'app OVANIE Livreur.
///
/// Reprend le même principe que le service équivalent de l'app client :
/// jamais bloquant, jamais fatal si Firebase n'est pas configuré sur cette
/// build (voir [configuredInApp]) — le livreur continue de découvrir ses
/// missions par rafraîchissement normal de la liste dans ce cas.
class PushNotificationService {
  PushNotificationService._();
  static final instance = PushNotificationService._();

  bool _booted = false;
  bool _registering = false;
  String? _token;
  DateTime? _lastRegistrationAt;
  StreamSubscription<String>? _tokenRefresh;

  bool get configuredInApp => OvanieDriverFirebaseOptions.configured;
  String? get token => _token;

  Future<void> initialize() async {
    if (_booted) return;
    _booted = true;

    try {
      if (OvanieDriverFirebaseOptions.configured) {
        await Firebase.initializeApp(options: OvanieDriverFirebaseOptions.current);
      } else {
        await Firebase.initializeApp();
      }
      FirebaseMessaging.onBackgroundMessage(
        ovanieDriverFirebaseMessagingBackgroundHandler,
      );

      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      _token = await messaging.getToken();

      _tokenRefresh = messaging.onTokenRefresh.listen((token) {
        _token = token;
        _lastRegistrationAt = null;
        unawaited(registerCurrentDevice(force: true));
      });

      await registerCurrentDevice();
    } catch (_) {
      // Firebase ne doit jamais empêcher l'utilisation générale de l'app
      // Livreur. Un prochain appel (ex: HomeScreen après connexion) pourra
      // retenter l'enregistrement.
    }
  }

  Future<bool> serverAvailable() async {
    if (!_booted) return false;
    try {
      final response = await ApiClient.dio.get<dynamic>('/driver/push');
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
    if (token == null || token.isEmpty || _registering) return;

    final savedToken = (await TokenStorage.instance.readToken())?.trim() ?? '';
    if (savedToken.isEmpty) return;

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
        '/driver/push/devices',
        data: {
          'token': token,
          'platform': platform,
          'device_name':
              platform == 'ios' ? 'iPhone OVANIE Livreur' : 'Android OVANIE Livreur',
          'app_version': '1.0.0+1',
        },
      );
      ApiClient.ensureSuccess(response);
      _lastRegistrationAt = DateTime.now();
    } catch (_) {
      // Un prochain retour sur l'écran d'accueil retentera l'enregistrement.
    } finally {
      _registering = false;
    }
  }

  Future<void> unregisterCurrentDevice() async {
    final token = _token;
    if (token == null || token.isEmpty) return;

    try {
      final response = await ApiClient.dio.delete<dynamic>(
        '/driver/push/devices',
        data: {'token': token},
      );
      ApiClient.ensureSuccess(response);
      _lastRegistrationAt = null;
    } catch (_) {}
  }

  Future<void> dispose() async {
    await _tokenRefresh?.cancel();
  }
}
