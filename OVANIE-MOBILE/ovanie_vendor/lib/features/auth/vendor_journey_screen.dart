import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/ui/ovanie_ui.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../onboarding/shop_onboarding_screen.dart';
import 'login_screen.dart';

/// Écran d'accueil : la photo du magasin avec « Bienvenue chez OVANIE », puis
/// le choix du parcours — se connecter ou ouvrir sa boutique.
class VendorJourneyScreen extends StatelessWidget {
  const VendorJourneyScreen({super.key});

  // Dans la maquette (941 px de large), la feuille blanche commence à 741 px.
  static const _photoAspect = 941 / 820;
  static const _sheetTop = 741 / 941;

  void _login(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LoginScreen()));
  }

  void _openShop(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const ShopOnboardingScreen()));
  }

  void _help(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Ov.surface,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(Ov.radiusSheet)),
      ),
      builder: (_) => const OvTheme(child: _HelpSheet()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);

    return MkTheme(
      child: Scaffold(
        backgroundColor: Colors.white,
        body: LayoutBuilder(
          builder: (context, viewport) {
            final width = math.min(viewport.maxWidth, Mk.maxWidth);
            final sheetTop = padding.top + width * _sheetTop;

            return SingleChildScrollView(
              child: Center(
                child: SizedBox(
                  width: width,
                  child: Stack(
                    children: <Widget>[
                      MkPhoto(
                        name: 'journey',
                        width: width,
                        aspect: _photoAspect,
                        label:
                            'OVANIE Vendeur. Bienvenue chez OVANIE, la marketplace BTP qui connecte les vendeurs et les acheteurs.',
                      ),
                      Padding(
                        padding: EdgeInsets.only(top: sheetTop),
                        child: Container(
                          constraints: BoxConstraints(minHeight: math.max(0, viewport.maxHeight - sheetTop)),
                          padding: EdgeInsets.fromLTRB(14, 10, 14, padding.bottom + 14),
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: <Widget>[
                              Center(
                                child: Container(
                                  width: 46,
                                  height: 5,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFC9CED8),
                                    borderRadius: BorderRadius.circular(3),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 18),
                              const Text(
                                'Quel est votre parcours ?',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: Mk.navy,
                                  fontSize: 22,
                                  fontWeight: FontWeight.w800,
                                  height: 1.2,
                                  letterSpacing: -.4,
                                ),
                              ),
                              const SizedBox(height: 8),
                              const Padding(
                                padding: EdgeInsets.symmetric(horizontal: 18),
                                child: Text(
                                  'Choisissez l’option qui correspond à votre situation pour continuer.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.35, fontWeight: FontWeight.w500),
                                ),
                              ),
                              const SizedBox(height: 16),
                              IntrinsicHeight(
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: <Widget>[
                                    Expanded(
                                      child: _JourneyCard(
                                        orange: false,
                                        icon: Icons.person_rounded,
                                        title: 'J’ai déjà un compte',
                                        text:
                                            'Connectez-vous pour accéder à votre espace vendeur, gérer votre boutique, vos produits et vos commandes.',
                                        action: 'Se connecter',
                                        onTap: () => _login(context),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: _JourneyCard(
                                        orange: true,
                                        icon: Icons.storefront_rounded,
                                        title: 'Ouvrir ma boutique',
                                        text:
                                            'Créez votre compte vendeur et ouvrez votre boutique sur OVANIE en quelques étapes simples.',
                                        action: 'Commencer',
                                        onTap: () => _openShop(context),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 14),
                              const MkDivider('Besoin d’aide ?'),
                              Center(
                                child: TextButton.icon(
                                  onPressed: () => _help(context),
                                  style: TextButton.styleFrom(
                                    foregroundColor: Mk.blue,
                                    minimumSize: const Size(48, 48),
                                    textStyle: const TextStyle(
                                      fontFamily: Mk.font,
                                      fontSize: 14.5,
                                      fontWeight: FontWeight.w600,
                                      decoration: TextDecoration.underline,
                                    ),
                                  ),
                                  icon: const Icon(Icons.headset_mic_rounded, size: 24),
                                  label: const Text('Contacter le support'),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Carte d'un parcours : pictogramme rond, titre, explication et bouton.
class _JourneyCard extends StatelessWidget {
  const _JourneyCard({
    required this.orange,
    required this.icon,
    required this.title,
    required this.text,
    required this.action,
    required this.onTap,
  });

  final bool orange;
  final IconData icon;
  final String title;
  final String text;
  final String action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = orange ? Mk.orange : Mk.blue;
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 14, 8, 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: orange
              ? const <Color>[Color(0xFFFFF3EA), Colors.white]
              : const <Color>[Color(0xFFEAF3FF), Colors.white],
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: accent.withValues(alpha: .38), width: 1.1),
      ),
      child: Column(
        children: <Widget>[
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: orange ? const Color(0xFFFFE6D3) : const Color(0xFFDCEBFD),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: orange ? Mk.orange : Mk.blueDeep, size: 32),
          ),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w800, height: 1.2),
          ),
          const SizedBox(height: 6),
          Expanded(
            child: Text(
              text,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Mk.muted, fontSize: 12, height: 1.4, fontWeight: FontWeight.w500),
            ),
          ),
          const SizedBox(height: 12),
          MkButton(label: action, blue: !orange, height: 44, onPressed: onTap),
        ],
      ),
    );
  }
}

/// Coordonnées officielles du support OVANIE.
class _HelpSheet extends StatelessWidget {
  const _HelpSheet();

  Future<void> _open(BuildContext context, String url) async {
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final opened = await ovOpenUrl(url);
    navigator.pop();
    if (!opened) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Impossible d’ouvrir ce lien sur ce téléphone.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(Ov.gutter, 0, Ov.gutter, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const Text(
              'Contacter le support',
              style: TextStyle(color: Ov.text, fontSize: 20, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            const Text(
              'Notre équipe vous répond 7 jours sur 7.',
              style: TextStyle(color: Ov.muted, fontSize: 14.5, height: 1.4),
            ),
            const SizedBox(height: 18),
            _HelpAction(
              icon: Icons.chat_outlined,
              color: const Color(0xFF128C4A),
              title: 'WhatsApp',
              value: '01 61 78 18 18',
              onTap: () => _open(context, 'https://wa.me/2250161781818'),
            ),
            const SizedBox(height: 10),
            _HelpAction(
              icon: Icons.call_outlined,
              color: Ov.blue,
              title: 'Appeler',
              value: '01 61 78 00 00',
              onTap: () => _open(context, 'tel:0161780000'),
            ),
          ],
        ),
      ),
    );
  }
}

class _HelpAction extends StatelessWidget {
  const _HelpAction({
    required this.icon,
    required this.color,
    required this.title,
    required this.value,
    required this.onTap,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Ov.fieldFill,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Ov.border, width: 1.2),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: <Widget>[
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(13)),
                child: Icon(icon, color: color, size: 23),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(title, style: const TextStyle(color: Ov.text, fontSize: 16, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text(value, style: const TextStyle(color: Ov.muted, fontSize: 14, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Ov.muted),
            ],
          ),
        ),
      ),
    );
  }
}
