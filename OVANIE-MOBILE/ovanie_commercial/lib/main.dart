import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app.dart';
import 'core/compatibility/schema_compatibility.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final schemaCompatibility = await OvanieSchemaCompatibility.check();
  if (schemaCompatibility.isIncompatible) {
    runApp(OvanieSchemaIncompatibleApp(result: schemaCompatibility));
    return;
  }

  await SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
    systemNavigationBarColor: Color(0xFF00102D),
    systemNavigationBarIconBrightness: Brightness.light,
  ));
  runApp(const OvanieCommercialApp());
}
