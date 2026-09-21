import 'package:flutter/material.dart';

import '../../../app/theme.dart';

/// Ecran affiché uniquement après un HTTP 401 reçu sur une requête qui portait
/// déjà un Bearer token OVANIE. Le Navigator courant n'est pas remplacé : la
/// reconnexion peut donc reprendre le parcours là où le client se trouvait.
class SessionExpiredScreen extends StatelessWidget {
  final Future<void> Function() onReconnect;

  const SessionExpiredScreen({
    super.key,
    required this.onReconnect,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: OvanieColors.background,
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(28, 36, 28, 36),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 86,
                    height: 86,
                    decoration: BoxDecoration(
                      color: OvanieColors.blue.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(28),
                    ),
                    child: const Icon(
                      Icons.lock_clock_outlined,
                      size: 42,
                      color: OvanieColors.blue,
                    ),
                  ),
                  const SizedBox(height: 22),
                  const Text(
                    'Votre session a expiré',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.text,
                      fontSize: 23,
                      height: 1.15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'OVANIE a confirmé que votre jeton de connexion n’est plus valide. Reconnectez-vous pour continuer.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 13.5,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Votre panier local et l’écran que vous consultiez sont conservés.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 12,
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: FilledButton.icon(
                      onPressed: () => onReconnect(),
                      style: FilledButton.styleFrom(
                        backgroundColor: OvanieColors.blue,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      icon: const Icon(Icons.login_rounded),
                      label: const Text(
                        'Se reconnecter',
                        style: TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
