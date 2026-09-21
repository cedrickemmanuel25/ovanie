import 'dart:async';

import 'package:flutter/material.dart';

import 'app/app.dart';
import 'core/network/api_client.dart';
import 'core/push/push_notification_service.dart';
import 'core/storage/token_storage.dart';
import 'core/compatibility/schema_compatibility.dart';
import 'core/reference_data/reference_data_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Ne bloque jamais le démarrage de l'app : voir
  // PushNotificationService.initialize() pour la gestion des erreurs.
  unawaited(PushNotificationService.instance.initialize());

  final schemaCompatibility = await OvanieSchemaCompatibility.check();
  if (schemaCompatibility.isIncompatible) {
    runApp(OvanieSchemaIncompatibleApp(result: schemaCompatibility));
    return;
  }

  await OvanieReferenceDataStore.instance.warmUp();

  final token = await TokenStorage.instance.readToken();
  if (token != null && token.isNotEmpty) {
    ApiClient.setBearerToken(token);
  }

  runApp(const OvanieLivreurApp());
}
