import 'package:flutter/material.dart';

import 'app/app.dart';
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

  runApp(const OvanieVendorApp());
}
