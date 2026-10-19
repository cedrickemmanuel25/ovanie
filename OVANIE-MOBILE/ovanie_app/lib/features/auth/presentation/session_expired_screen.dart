import 'package:flutter/material.dart';

import 'auth_branding.dart';

/// Ecran affiché uniquement après un HTTP 401 reçu sur une requête qui portait
/// déjà un Bearer token OVANIE. Le Navigator courant n'est pas remplacé : la
/// reconnexion peut donc reprendre le parcours là où le client se trouvait.
class SessionExpiredScreen extends StatelessWidget {
  final Future<void> Function() onReconnect;

  const SessionExpiredScreen({super.key, required this.onReconnect});

  @override
  Widget build(BuildContext context) {
    return OvanieStatusPage(
      art: 'session',
      artAspect: 941 / 795,
      title: 'Votre session a expiré',
      message: 'Par sécurité, reconnectez-vous pour continuer.',
      extras: const [
        OvanieInfoStrip(
          icon: Icons.shopping_cart_outlined,
          green: false,
          text: 'Votre panier local et l’écran que vous consultiez sont conservés.',
        ),
      ],
      button: OvaniePrimaryButton(label: 'Se reconnecter', height: 54, onPressed: () => onReconnect()),
    );
  }
}
