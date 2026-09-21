import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/ui/vendor_design.dart';
import '../../core/ui/vendor_wizard_ui.dart';
import '../onboarding/shop_onboarding_screen.dart';
import 'login_screen.dart';

class VendorJourneyScreen extends StatelessWidget {
  const VendorJourneyScreen({super.key});

  void _login(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen()));
  }

  void _openShop(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ShopOnboardingScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final heroHeight = width * (590 / 941);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SingleChildScrollView(
          child: Column(
            children: <Widget>[
              SizedBox(
                width: double.infinity,
                height: heroHeight,
                child: Image.asset(
                  'assets/images/vendor_journey_header.jpg',
                  fit: BoxFit.cover,
                  alignment: Alignment.topCenter,
                ),
              ),
              Transform.translate(
                offset: const Offset(0, -2),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(34, 14, 34, 30),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
                  ),
                  child: Column(
                    children: <Widget>[
                      Container(
                        width: 42,
                        height: 5,
                        decoration: BoxDecoration(color: const Color(0xFFD7D9DE), borderRadius: BorderRadius.circular(99)),
                      ),
                      const SizedBox(height: 26),
                      const Text(
                        'Quel est votre parcours ?',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: vendorText, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: -.5),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Choisissez l’option qui correspond à votre situation pour continuer.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: vendorMuted, fontSize: 16, height: 1.35),
                      ),
                      const SizedBox(height: 30),
                      LayoutBuilder(
                        builder: (context, constraints) {
                          // Le parcours est dans un SingleChildScrollView : la hauteur
                          // reçue ici est donc non bornée. Un Row en `stretch` avec
                          // IntrinsicHeight/Spacer provoque une contrainte verticale
                          // infinie en release (écran blanc juste après le splash).
                          // On donne donc une hauteur finie et fixe aux deux cartes.
                          final cardWidth = (constraints.maxWidth - 14) / 2;
                          final cardsHeight = cardWidth < 170 ? 300.0 : 340.0;
                          return SizedBox(
                            height: cardsHeight,
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: <Widget>[
                                Expanded(
                                  child: _JourneyCard(
                                    icon: Icons.person_outline_rounded,
                                    title: 'J’ai déjà\nun compte',
                                    description: 'Connectez-vous pour accéder à votre espace vendeur, gérer votre boutique, vos produits et vos commandes.',
                                    buttonLabel: 'Se connecter',
                                    blue: true,
                                    onTap: () => _login(context),
                                  ),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: _JourneyCard(
                                    icon: Icons.storefront_outlined,
                                    title: 'Ouvrir\nma boutique',
                                    description: 'Créez votre compte vendeur et ouvrez votre boutique sur OVANIE en quelques étapes simples.',
                                    buttonLabel: 'Commencer',
                                    blue: false,
                                    onTap: () => _openShop(context),
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                      const SizedBox(height: 42),
                      Row(
                        children: <Widget>[
                          const Expanded(child: Divider(color: Color(0xFFD4D9E1))),
                          const Padding(
                            padding: EdgeInsets.symmetric(horizontal: 18),
                            child: Text('Besoin d’aide ?', style: TextStyle(color: vendorText, fontSize: 15.5)),
                          ),
                          const Expanded(child: Divider(color: Color(0xFFD4D9E1))),
                        ],
                      ),
                      const SizedBox(height: 12),
                      const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: <Widget>[
                          Icon(Icons.headset_mic_outlined, color: Color(0xFF073FE2), size: 31),
                          SizedBox(width: 10),
                          Text('Contacter le support', style: TextStyle(color: Color(0xFF073FE2), fontSize: 15.5, fontWeight: FontWeight.w600)),
                        ],
                      ),
                      SizedBox(height: MediaQuery.paddingOf(context).bottom + 6),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _JourneyCard extends StatelessWidget {
  const _JourneyCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.buttonLabel,
    required this.blue,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String description;
  final String buttonLabel;
  final bool blue;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = blue ? const Color(0xFF0A3A8E) : wizardOrange;
    final background = blue ? const Color(0xFFF8FBFF) : const Color(0xFFFFFAF5);
    final border = blue ? const Color(0xFFDDE9FB) : const Color(0xFFFFDDC5);

    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 170;
        return Container(
          padding: EdgeInsets.fromLTRB(compact ? 10 : 14, 18, compact ? 10 : 14, 14),
          decoration: BoxDecoration(
            color: background,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: border, width: 1.2),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: compact ? 62 : 84,
                height: compact ? 62 : 84,
                alignment: Alignment.center,
                decoration: BoxDecoration(shape: BoxShape.circle, color: accent.withValues(alpha: .09)),
                child: Icon(icon, color: accent, size: compact ? 32 : 42),
              ),
              SizedBox(height: compact ? 12 : 16),
              Text(
                title,
                textAlign: TextAlign.center,
                style: TextStyle(color: vendorText, fontSize: compact ? 16.5 : 20, fontWeight: FontWeight.w900, height: 1.08),
              ),
              SizedBox(height: compact ? 8 : 12),
              Text(
                description,
                textAlign: TextAlign.center,
                style: TextStyle(color: vendorMuted, fontSize: compact ? 11 : 13, height: 1.4),
              ),
              SizedBox(height: compact ? 16 : 20),
              SizedBox(
                width: double.infinity,
                height: compact ? 44 : 50,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: blue
                          ? const <Color>[Color(0xFF062760), Color(0xFF073B8B)]
                          : const <Color>[Color(0xFFFF7800), Color(0xFFFF5900)],
                    ),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Material(
                    color: Colors.transparent,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(10),
                      onTap: onTap,
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        child: Row(
                          children: <Widget>[
                            Expanded(
                              child: Text(
                                buttonLabel,
                                textAlign: TextAlign.center,
                                style: TextStyle(color: Colors.white, fontSize: compact ? 13 : 15, fontWeight: FontWeight.w900),
                              ),
                            ),
                            Icon(Icons.arrow_forward_rounded, color: Colors.white, size: compact ? 20 : 23),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
