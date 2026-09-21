import 'package:flutter/services.dart';

/// Stockage persistant natif léger utilisé par OVANIE.
///
/// Android enregistre les valeurs dans SharedPreferences via MainActivity.
/// Le backend Laravel reste la source de vérité pour les données métier,
/// mais ce stockage permet de conserver le panier invité et la session mobile
/// entre deux ouvertures de l'application.
class DeviceStorage {
  DeviceStorage._();

  static final DeviceStorage instance = DeviceStorage._();

  static const MethodChannel _channel = MethodChannel('ovanie/device_storage');

  Future<String?> readString(String key) async {
    try {
      return await _channel.invokeMethod<String>(
        'readString',
        <String, dynamic>{'key': key},
      );
    } on PlatformException {
      return null;
    } on MissingPluginException {
      return null;
    } catch (_) {
      return null;
    }
  }

  Future<void> writeString(String key, String value) async {
    try {
      await _channel.invokeMethod<dynamic>(
        'writeString',
        <String, dynamic>{
          'key': key,
          'value': value,
        },
      );
    } on PlatformException {
      // La valeur reste disponible en mémoire pour la session courante.
    } on MissingPluginException {
      // Plateforme sans implémentation native.
    } catch (_) {
      // Le stockage local ne doit jamais faire tomber l’application.
    }
  }

  Future<void> remove(String key) async {
    try {
      await _channel.invokeMethod<dynamic>(
        'remove',
        <String, dynamic>{'key': key},
      );
    } on PlatformException {
      // Ne jamais bloquer l’UI pour un échec de nettoyage local.
    } on MissingPluginException {
      // Plateforme sans implémentation native.
    } catch (_) {
      // Ignoré volontairement.
    }
  }
}
