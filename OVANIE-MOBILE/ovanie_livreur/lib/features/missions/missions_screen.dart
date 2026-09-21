import 'package:flutter/material.dart';

import 'screens/mission_list_screen.dart';

/// Espace Missions de l'application Livreur.
///
/// Le Navigator local conserve tous les écrans du parcours mission sous
/// l'onglet « Missions ». Le [navigatorKey] permet notamment à l'onglet Suivi
/// d'ouvrir directement la mission actuellement suivie sans masquer la barre
/// de navigation principale.
class MissionsScreen extends StatelessWidget {
  const MissionsScreen({
    super.key,
    this.navigatorKey,
  });

  final GlobalKey<NavigatorState>? navigatorKey;

  @override
  Widget build(BuildContext context) {
    return Navigator(
      key: navigatorKey,
      onGenerateRoute: (_) => MaterialPageRoute<void>(
        builder: (_) => const MissionListScreen(),
      ),
    );
  }
}
