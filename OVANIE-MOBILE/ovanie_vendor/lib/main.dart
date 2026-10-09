import 'dart:async';

import 'package:flutter/material.dart';
import 'core/sync/live_sync_service.dart';

import 'app/app.dart';
import 'core/compatibility/schema_compatibility.dart';
import 'core/reference_data/reference_data_store.dart';
import 'features/splash/splash_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const _OvanieRoot());
}

/// Affiche l'application tout de suite : l'écran de lancement reste visible
/// pendant les vérifications de démarrage, au lieu d'un écran vide.
class _OvanieRoot extends StatefulWidget {
  const _OvanieRoot();

  @override
  State<_OvanieRoot> createState() => _OvanieRootState();
}

class _OvanieRootState extends State<_OvanieRoot> {
  SchemaCompatibilityResult? _incompatible;

  @override
  void initState() {
    super.initState();
    final ready = Completer<void>();
    SplashScreen.startup = ready.future;
    _start(ready);
  }

  Future<void> _start(Completer<void> ready) async {
    final schemaCompatibility = await OvanieSchemaCompatibility.check();
    if (schemaCompatibility.isIncompatible) {
      // L'écran de lancement ne se termine pas : il est remplacé par le message.
      if (mounted) setState(() => _incompatible = schemaCompatibility);
      return;
    }

    try {
      await OvanieReferenceDataStore.instance.warmUp();
    } finally {
      // Commandes, produits et notifications se mettent à jour seuls quand ils
      // changent sur l'espace vendeur web.
      LiveSyncService.instance.start();
      ready.complete();
    }
  }

  @override
  Widget build(BuildContext context) {
    final incompatible = _incompatible;
    if (incompatible != null) return OvanieSchemaIncompatibleApp(result: incompatible);
    return const OvanieVendorApp();
  }
}
