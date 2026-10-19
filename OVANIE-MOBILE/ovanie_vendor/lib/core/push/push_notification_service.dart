import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../network/api_client.dart';

/// Options Firebase de l'app OVANIE Vendeur, fournies au build via
/// `--dart-define` (même projet Firebase que les apps Client et Livreur ;
/// seul `appId` est propre à cette application). Un fichier
/// android/app/google-services.json fonctionne aussi.
class OvanieVendorFirebaseOptions {
  OvanieVendorFirebaseOptions._();

  static const apiKey = String.fromEnvironment('OVANIE_FIREBASE_API_KEY');
  static const appId = String.fromEnvironment('OVANIE_VENDOR_FIREBASE_APP_ID');
  static const messagingSenderId = String.fromEnvironment('OVANIE_FIREBASE_MESSAGING_SENDER_ID');
  static const projectId = String.fromEnvironment('OVANIE_FIREBASE_PROJECT_ID');

  static bool get configured =>
      apiKey.isNotEmpty && appId.isNotEmpty && messagingSenderId.isNotEmpty && projectId.isNotEmpty;

  static FirebaseOptions get current => const FirebaseOptions(
        apiKey: apiKey,
        appId: appId,
        messagingSenderId: messagingSenderId,
        projectId: projectId,
      );
}

Future<void> _initializeFirebase() async {
  if (Firebase.apps.isNotEmpty) return;
  if (OvanieVendorFirebaseOptions.configured) {
    await Firebase.initializeApp(options: OvanieVendorFirebaseOptions.current);
  } else {
    await Firebase.initializeApp();
  }
}

@pragma('vm:entry-point')
Future<void> ovanieVendorFirebaseBackgroundHandler(RemoteMessage message) async {
  // Android affiche lui-même la notification à partir du bloc `notification`
  // FCM ; à la réouverture, les écrans se rechargent depuis le serveur.
  await _initializeFirebase();
}

/// Enregistre ce téléphone auprès d'OVANIE pour recevoir les alertes
/// instantanées (nouvelle commande, retour, avis, versement…).
///
/// Jamais bloquant : si Firebase n'est pas configuré sur cette installation,
/// les alertes restent consultables dans l'onglet Notifications.
class PushNotificationService {
  PushNotificationService._();
  static final instance = PushNotificationService._();

  bool _booted = false;
  bool _firebaseReady = false;
  bool _registering = false;
  bool registered = false;
  String? _token;
  StreamSubscription<String>? _tokenRefresh;

  /// Faux quand cette installation n'a aucune configuration Firebase.
  bool get configuredInApp => _firebaseReady || OvanieVendorFirebaseOptions.configured;

  Future<void> initialize({bool force = false}) async {
    if (_booted && !force) return;
    _booted = true;
    try {
      await _initializeFirebase();
      _firebaseReady = true;
      FirebaseMessaging.onBackgroundMessage(ovanieVendorFirebaseBackgroundHandler);
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      _token = await messaging.getToken();
      _tokenRefresh ??= messaging.onTokenRefresh.listen((token) {
        _token = token;
        unawaited(_register());
      });
      await _register();
    } catch (error) {
      if (kDebugMode) debugPrint('Alertes OVANIE indisponibles : $error');
    }
  }

  Future<void> _register() async {
    final token = _token;
    if (token == null || token.isEmpty || _registering) return;
    _registering = true;
    try {
      final status = await ApiClient.dio.get<dynamic>('/mobile/v1/vendor/push');
      ApiClient.ensureSuccess(status);
      final data = status.data is Map ? (status.data as Map)['data'] : null;
      if (data is! Map || data['available'] != true) return;
      final response = await ApiClient.dio.post<dynamic>('/mobile/v1/vendor/push/devices', data: {
        'token': token,
        'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
        'device_name': defaultTargetPlatform == TargetPlatform.iOS ? 'iPhone OVANIE Vendeur' : 'Android OVANIE Vendeur',
        'app_version': '0.1.11',
      });
      ApiClient.ensureSuccess(response);
      registered = true;
    } catch (_) {
      // Nouvel essai au prochain lancement ou depuis « Sécurité du compte ».
    } finally {
      _registering = false;
    }
  }

  /// À la déconnexion : ce téléphone ne doit plus recevoir les alertes du compte.
  Future<void> unregister() async {
    final token = _token;
    if (token == null || token.isEmpty || !registered) return;
    try {
      await ApiClient.dio.delete<dynamic>('/mobile/v1/vendor/push/devices', data: {'token': token});
    } catch (_) {}
    registered = false;
  }
}
