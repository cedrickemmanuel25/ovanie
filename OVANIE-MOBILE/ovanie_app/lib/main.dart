import 'package:flutter/material.dart';

import 'app/app.dart';
import 'core/lifecycle/app_lifecycle_persistence.dart';
import 'core/push/push_notification_service.dart';
import 'core/compatibility/schema_compatibility.dart';
import 'core/reference_data/reference_data_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final schemaCompatibility = await OvanieSchemaCompatibility.check();
  if (schemaCompatibility.isIncompatible) {
    runApp(OvanieSchemaIncompatibleApp(result: schemaCompatibility));
    return;
  }

  await OvanieReferenceDataStore.instance.warmUp();

  // Le Splash réalise maintenant l'initialisation ordonnée : session locale,
  // panier local, vérification Laravel, profil puis panier serveur.
  AppLifecyclePersistence.instance.start();
  await PushNotificationService.instance.initialize();

  PaintingBinding.instance.imageCache.maximumSize = 80;
  PaintingBinding.instance.imageCache.maximumSizeBytes = 40 * 1024 * 1024;

  runApp(const OvanieApp());
}
