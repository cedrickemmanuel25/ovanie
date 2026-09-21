import 'package:flutter/material.dart';

import '../models/menu_data.dart';
import 'menu_shared.dart';

class CommercialAboutScreen extends StatelessWidget {
  const CommercialAboutScreen({
    super.key,
    required this.initialUser,
    required this.initialProfile,
    required this.unreadNotifications,
  });

  final Map<String, dynamic> initialUser;
  final MenuProfileData? initialProfile;
  final int unreadNotifications;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final profile = menuHeaderProfile(initialUser: initialUser, loaded: initialProfile);
    return MenuPageScaffold(
      profile: profile,
      unreadNotifications: unreadNotifications,
      body: ListView(
        padding: EdgeInsets.fromLTRB(s(14), s(10), s(14), s(102)),
        children: [
          const MenuSearchBar(hint: 'Rechercher une boutique, un produit, une référence...'),
          SizedBox(height: s(12)),
          MenuPageTitle(
            title: 'À propos',
            subtitle: 'Découvrez l’application OVANIE Commercial et ses informations utiles.',
            onBack: () => Navigator.pop(context),
          ),
          SizedBox(height: s(12)),
          DarkMenuCard(
            child: Row(
              children: [
                Container(
                  width: s(72),
                  height: s(72),
                  padding: EdgeInsets.all(s(8)),
                  decoration: BoxDecoration(
                    color: const Color(0xFF001633),
                    borderRadius: BorderRadius.circular(s(12)),
                    border: Border.all(color: const Color(0xFF37567D)),
                  ),
                  child: Image.asset('assets/images/ovanie_logo.png', fit: BoxFit.contain),
                ),
                SizedBox(width: s(14)),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('OVANIE Commercial', style: TextStyle(color: Colors.white, fontSize: s(17), fontWeight: FontWeight.w800)),
                      SizedBox(height: s(4)),
                      Text(
                        'Application terrain pour la création de boutiques\net la capture de produits',
                        style: TextStyle(color: const Color(0xFFC4CEE0), fontSize: s(10.6), height: 1.35),
                      ),
                      SizedBox(height: s(7)),
                      Container(
                        padding: EdgeInsets.symmetric(horizontal: s(8), vertical: s(4)),
                        decoration: BoxDecoration(
                          color: const Color(0xFF073669),
                          borderRadius: BorderRadius.circular(s(7)),
                          border: Border.all(color: const Color(0xFF0E73CE)),
                        ),
                        child: Text('Version 1.0.0', style: TextStyle(color: menuBlue, fontSize: s(9.6))),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: s(10)),
          DarkMenuCard(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: s(48), height: s(48),
                  decoration: BoxDecoration(color: const Color(0xFF063B75), shape: BoxShape.circle),
                  child: Icon(Icons.track_changes_rounded, color: menuBlue, size: s(28)),
                ),
                SizedBox(width: s(14)),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Notre mission', style: TextStyle(color: menuBlue, fontSize: s(13), fontWeight: FontWeight.w600)),
                      SizedBox(height: s(5)),
                      Text(
                        'OVANIE Commercial accompagne les agents terrain dans la création de boutiques, la visite des clients, la capture de produits et la complétion d’informations produits de manière rapide, fiable et professionnelle.',
                        style: TextStyle(color: const Color(0xFFC8D1E1), fontSize: s(10.8), height: 1.4),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: s(10)),
          _AboutGroup(
            title: 'Fonctionnalités principales',
            rows: const [
              _AboutRow(Icons.storefront_outlined, 'Ouverture de boutique', 'Créer et accompagner de nouveaux vendeurs'),
              _AboutRow(Icons.storefront_outlined, 'Boutiques visitées', 'Suivre les visites et l’activité terrain'),
              _AboutRow(Icons.camera_alt_outlined, 'Sessions produits', 'Photographier plusieurs produits et reprendre la saisie plus tard'),
              _AboutRow(Icons.note_add_outlined, 'Produits en brouillon', 'Retrouver et finaliser les fiches incomplètes'),
            ],
          ),
          SizedBox(height: s(10)),
          _AboutGroup(
            title: 'Informations utiles',
            rows: const [
              _AboutRow(Icons.business_outlined, 'Éditeur', 'OVANIE', valueRight: true),
              _AboutRow(Icons.location_on_outlined, 'Zone d’activité', 'Côte d’Ivoire', valueRight: true),
              _AboutRow(Icons.support_agent_rounded, 'Support', '01 61 78 18 18', valueRight: true),
              _AboutRow(Icons.phone_outlined, 'WhatsApp', '01 61 78 18 18', valueRight: true),
            ],
          ),
          SizedBox(height: s(10)),
          _AboutGroup(
            title: 'Liens utiles',
            rows: [
              _AboutRow(Icons.help_outline_rounded, 'Centre d’aide', '', onTap: () => _info(context, 'Centre d’aide', 'Contactez le support OVANIE au 01 61 78 18 18.')),
              _AboutRow(Icons.shield_outlined, 'Politique de confidentialité', '', onTap: () => _info(context, 'Politique de confidentialité', 'La politique de confidentialité OVANIE est disponible sur les canaux officiels de la plateforme.')),
              _AboutRow(Icons.description_outlined, 'Conditions d’utilisation', '', onTap: () => _info(context, 'Conditions d’utilisation', 'Les conditions d’utilisation OVANIE sont disponibles sur les canaux officiels de la plateforme.')),
            ],
          ),
          SizedBox(height: s(10)),
          DarkMenuCard(
            padding: EdgeInsets.symmetric(vertical: s(10)),
            child: Center(child: Text('ⓘ  © 2026 OVANIE. Tous droits réservés.', style: TextStyle(color: const Color(0xFFC2CCDE), fontSize: s(9.8)))),
          ),
        ],
      ),
    );
  }

  static void _info(BuildContext context, String title, String message) {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Fermer'))],
      ),
    );
  }
}

class _AboutGroup extends StatelessWidget {
  const _AboutGroup({required this.title, required this.rows});
  final String title;
  final List<_AboutRow> rows;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return DarkMenuCard(
      padding: EdgeInsets.fromLTRB(s(12), s(9), s(12), s(7)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          MenuSectionTitle(title),
          SizedBox(height: s(5)),
          for (var i = 0; i < rows.length; i++) ...[
            rows[i],
            if (i != rows.length - 1) Divider(height: 1, color: const Color(0xFF36577E)),
          ],
        ],
      ),
    );
  }
}

class _AboutRow extends StatelessWidget {
  const _AboutRow(this.icon, this.title, this.subtitle, {this.valueRight = false, this.onTap});
  final IconData icon;
  final String title;
  final String subtitle;
  final bool valueRight;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: s(7)),
        child: Row(
          children: [
            Icon(icon, color: Colors.white, size: s(20)),
            SizedBox(width: s(11)),
            Expanded(
              child: valueRight
                  ? Text(title, style: TextStyle(color: const Color(0xFFD0D8E6), fontSize: s(10.5)))
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(title, style: TextStyle(color: Colors.white, fontSize: s(11.4), fontWeight: FontWeight.w600)),
                        if (subtitle.isNotEmpty) Text(subtitle, style: TextStyle(color: const Color(0xFFB9C5D9), fontSize: s(9.3))),
                      ],
                    ),
            ),
            if (valueRight)
              Text(subtitle, textAlign: TextAlign.right, style: TextStyle(color: const Color(0xFFD0D8E6), fontSize: s(10.6)))
            else if (onTap != null)
              Icon(Icons.chevron_right_rounded, color: Colors.white, size: s(21)),
          ],
        ),
      ),
    );
  }
}
