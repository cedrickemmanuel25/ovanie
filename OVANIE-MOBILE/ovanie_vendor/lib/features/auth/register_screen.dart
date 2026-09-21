import 'package:flutter/material.dart';

import '../onboarding/shop_onboarding_screen.dart';

/// Compatibilité avec les anciens liens internes qui pointaient vers
/// `RegisterScreen`.
///
/// Le nouveau parcours vendeur fusionne désormais la création du compte et
/// l'ouverture de la boutique dans les 3 étapes de `ShopOnboardingScreen`.
class RegisterScreen extends StatelessWidget {
  const RegisterScreen({super.key});

  @override
  Widget build(BuildContext context) => const ShopOnboardingScreen();
}
