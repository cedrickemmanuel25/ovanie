import 'package:flutter/material.dart';

import '../../../app/theme.dart';

/// Écran d'attente générique pour un onglet dont l'API n'est pas encore
/// branchée côté backend. Volontairement sans donnée fictive.
class ComingSoonScreen extends StatelessWidget {
  const ComingSoonScreen({
    super.key,
    required this.title,
    required this.icon,
    required this.message,
  });

  final String title;
  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: OvanieColors.background,
      appBar: AppBar(title: Text(title)),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 48, color: OvanieColors.green),
                const SizedBox(height: 16),
                const Text(
                  'Bientôt disponible',
                  style: TextStyle(
                    color: OvanieColors.text,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: OvanieColors.muted, fontSize: 13.5, height: 1.4),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
