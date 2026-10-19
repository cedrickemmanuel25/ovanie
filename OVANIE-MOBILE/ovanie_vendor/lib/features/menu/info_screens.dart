import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/reference_data/reference_data_store.dart';
import '../../core/ui/mk_dialogs.dart';
import '../support/vendor_support_screen.dart';
import 'menu_ui.dart';

const _accent = Color(0xFFE85400);
const _line = Color(0xFFE4E9F1);

Widget _card({required Widget child, EdgeInsets padding = const EdgeInsets.all(14), Color? accent, Color color = Colors.white}) => Container(
  margin: const EdgeInsets.only(bottom: 10),
  clipBehavior: Clip.antiAlias,
  decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(14)),
  child: accent == null
      ? Padding(padding: padding, child: child)
      : IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(width: 4, color: accent),
              Expanded(child: Padding(padding: padding, child: child)),
            ],
          ),
        ),
);

void _openSupport(BuildContext context) =>
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const VendorSupportScreen()));

Future<void> _call(BuildContext context, String telUrl) async {
  try {
    await launchUrl(Uri.parse(telUrl));
  } catch (e) {
    if (context.mounted) menuError(context, e);
  }
}

void _copyEmail(BuildContext context, String email) {
  Clipboard.setData(ClipboardData(text: email));
  mkToast(context, 'Adresse e-mail copiée.');
}

// ============================================================================
// Conditions d'utilisation
// ============================================================================

/// Conditions générales d'utilisation : mêmes textes que le site OVANIE,
/// avec un sommaire dépliable et les articles qui concernent le vendeur.
class TermsScreen extends StatefulWidget {
  const TermsScreen({super.key});
  @override
  State<TermsScreen> createState() => _TermsState();
}

class _TermsState extends State<TermsScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;
  String? error;
  int? open;
  final _links = <TapGestureRecognizer>[];

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    for (final link in _links) {
      link.dispose();
    }
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final r = await ApiClient.dio.get<dynamic>('/mobile/v1/legal/terms');
      ApiClient.ensureSuccess(r);
      data = mapOf(r.data);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  /// Texte d'un article ; le téléphone et l'e-mail d'OVANIE y sont actifs.
  InlineSpan _linked(String text) {
    final contact = OvanieReferenceDataStore.instance.contact;
    final served = mapOf(data['contact']);
    final phone = screenText(served['phone_display'], contact.phoneDisplay);
    final email = screenText(served['email'], contact.email);
    final spans = <InlineSpan>[];
    var rest = text;
    while (rest.isNotEmpty) {
      final iPhone = phone.isEmpty ? -1 : rest.indexOf(phone);
      final iEmail = email.isEmpty ? -1 : rest.indexOf(email);
      final hits = [iPhone, iEmail].where((i) => i >= 0).toList()..sort();
      if (hits.isEmpty) {
        spans.add(TextSpan(text: rest));
        break;
      }
      final at = hits.first;
      final isPhone = at == iPhone;
      final token = isPhone ? phone : email;
      if (at > 0) spans.add(TextSpan(text: rest.substring(0, at)));
      final recognizer = TapGestureRecognizer()
        ..onTap = () => isPhone ? _call(context, contact.telUrl) : _copyEmail(context, email);
      _links.add(recognizer);
      spans.add(
        TextSpan(
          text: token,
          recognizer: recognizer,
          style: const TextStyle(color: Mk.blue, fontWeight: FontWeight.w600, decoration: TextDecoration.underline),
        ),
      );
      rest = rest.substring(at + token.length);
    }
    return TextSpan(children: spans);
  }

  static const _icons = <IconData>[
    Icons.article_outlined,
    Icons.storefront_outlined,
    Icons.person_outline_rounded,
    Icons.sell_outlined,
    Icons.shopping_bag_outlined,
    Icons.credit_card_outlined,
    Icons.local_shipping_outlined,
    Icons.cancel_outlined,
    Icons.assignment_return_outlined,
    Icons.verified_user_outlined,
    Icons.card_giftcard_rounded,
    Icons.groups_outlined,
    Icons.gavel_rounded,
    Icons.balance_rounded,
    Icons.copyright_rounded,
    Icons.lock_outline_rounded,
    Icons.edit_note_rounded,
  ];

  @override
  Widget build(BuildContext context) {
    for (final link in _links) {
      link.dispose();
    }
    _links.clear();
    final sections = rowsOf(data['sections']);
    // Mis en avant sous le sommaire : l'objet, le rôle d'OVANIE et l'article des vendeurs.
    final vendors = sections.indexWhere((s) => '${s['title']}'.toLowerCase().startsWith('vendeur'));
    final featured = <int>{if (sections.isNotEmpty) 0, if (sections.length > 1) 1, if (vendors >= 0) vendors}.toList();

    return MenuPage(
      title: 'Conditions d’utilisation',
      subtitle: 'Nos règles et engagements',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: sections.isNotEmpty,
      children: [
        _card(
          padding: EdgeInsets.zero,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AspectRatio(
                aspectRatio: 755 / 235,
                child: Image.asset('assets/images/mockups/legal_hero.jpg', fit: BoxFit.cover, excludeFromSemantics: true),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      screenText(data['title'], 'Conditions générales d’utilisation'),
                      style: const TextStyle(color: menuBlue, fontSize: 19, height: 1.2, fontWeight: FontWeight.w800, letterSpacing: -.3),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      screenText(data['intro'], ''),
                      style: const TextStyle(color: menuMuted, fontSize: 13, height: 1.45, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        _card(
          padding: const EdgeInsets.fromLTRB(14, 12, 10, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Sommaire',
                style: TextStyle(color: menuBlue, fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -.2),
              ),
              const SizedBox(height: 4),
              for (var i = 0; i < sections.length; i++) _summaryRow(sections[i], i, last: i == sections.length - 1),
            ],
          ),
        ),
        for (final i in featured) _article(sections[i], i, highlight: i == vendors),
        _card(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              menuIcon(Icons.headset_mic_rounded, color: menuOrange, size: 42),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Besoin d’aide ?',
                      style: TextStyle(color: menuBlue, fontSize: 15.5, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Notre équipe répond à vos questions.',
                      style: TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 10),
                    MkBarButton(label: 'Contacter OVANIE', filled: true, height: 44, onTap: () => _openSupport(context)),
                  ],
                ),
              ),
            ],
          ),
        ),
        Container(
          margin: const EdgeInsets.only(bottom: 6),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
          decoration: BoxDecoration(color: const Color(0xFFDDEAFD), borderRadius: BorderRadius.circular(99)),
          child: const Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.verified_user_outlined, color: menuBlue, size: 20),
              SizedBox(width: 8),
              Flexible(
                child: Text(
                  'Achetez et utilisez OVANIE en toute confiance',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: menuBlue, fontSize: 13, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _summaryRow(Map<String, dynamic> section, int index, {required bool last}) {
    final expanded = open == index;
    final color = expanded ? _accent : menuBlue;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          button: true,
          expanded: expanded,
          child: InkWell(
            onTap: () => setState(() => open = expanded ? null : index),
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: BoxDecoration(
                border: Border(bottom: BorderSide(color: last || expanded ? Colors.transparent : _line)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 34,
                    padding: const EdgeInsets.symmetric(vertical: 2),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: expanded ? const Color(0xFFFFE8D9) : const Color(0xFFE6EEFB),
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      '${index + 1}',
                      style: TextStyle(color: color, fontSize: 12.5, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Text(
                      screenText(section['title'], ''),
                      style: TextStyle(color: color, fontSize: 14, fontWeight: expanded ? FontWeight.w700 : FontWeight.w500),
                    ),
                  ),
                  Icon(expanded ? Icons.expand_less_rounded : Icons.expand_more_rounded, color: expanded ? _accent : Mk.icon, size: 22),
                  const SizedBox(width: 4),
                ],
              ),
            ),
          ),
        ),
        if (expanded)
          Container(
            margin: const EdgeInsets.fromLTRB(24, 0, 4, 8),
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
            decoration: BoxDecoration(color: const Color(0xFFF1F4F9), borderRadius: BorderRadius.circular(10)),
            child: Text.rich(
              _linked(screenText(section['text'], '')),
              style: const TextStyle(color: Mk.icon, fontSize: 13, height: 1.5, fontWeight: FontWeight.w500),
            ),
          ),
      ],
    );
  }

  Widget _article(Map<String, dynamic> section, int index, {required bool highlight}) => _card(
    accent: highlight ? Mk.orange : null,
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        menuIcon(_icons[index % _icons.length], color: highlight ? menuOrange : menuAzure, size: 44),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${index + 1}. ${screenText(section['title'], '')}',
                style: const TextStyle(color: menuBlue, fontSize: 16, fontWeight: FontWeight.w800, letterSpacing: -.2),
              ),
              const SizedBox(height: 6),
              Text.rich(
                _linked(screenText(section['text'], '')),
                style: const TextStyle(color: menuMuted, fontSize: 13, height: 1.5, fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

// ============================================================================
// À propos d'OVANIE
// ============================================================================

/// Présentation d'OVANIE : mission, publics, services, principes et contact.
class AboutScreen extends StatefulWidget {
  const AboutScreen({super.key, required this.version});
  final String version;
  @override
  State<AboutScreen> createState() => _AboutState();
}

class _AboutState extends State<AboutScreen> {
  int _principle = 0;

  static const _audiences = <(String, String, String)>[
    ('about_clients', 'Clients', 'Trouvez facilement les bons produits au meilleur prix et faites-vous livrer sur vos chantiers.'),
    ('about_vendors', 'Vendeurs', 'Développez votre activité, touchez plus de clients et gérez vos ventes efficacement.'),
    ('about_sites', 'Chantiers', 'Suivez vos besoins, comparez les offres et recevez vos matériaux où vous en avez besoin.'),
    ('about_pros', 'Professionnels', 'Accédez à des outils, services et partenaires pour faire grandir vos projets en toute sérénité.'),
  ];
  static const _benefits = <String>[
    'Augmentez votre visibilité auprès de milliers d’acheteurs.',
    'Gérez vos produits, stocks et commandes simplement.',
    'Profitez d’outils performants pour piloter votre activité.',
    'Recevez vos paiements en toute sécurité.',
    'Bénéficiez d’un accompagnement dédié.',
    'Développez votre réseau et vos opportunités.',
  ];
  static const _features = <(IconData, Color, String, String)>[
    (
      Icons.view_in_ar_outlined,
      menuAzure,
      'Catalogue BTP',
      'Un large choix de matériaux et équipements pour tous types de projets, sélectionnés auprès de vendeurs de confiance.',
    ),
    (
      Icons.verified_user_outlined,
      menuGreen,
      'Achat sécurisé',
      'Paiement en ligne sécurisé, protection des données et transparence à chaque étape de votre commande.',
    ),
    (
      Icons.local_shipping_outlined,
      menuOrange,
      'Livraison adaptée',
      'Livraison rapide et flexible partout en Côte d’Ivoire, avec suivi de commande jusqu’à destination.',
    ),
    (
      Icons.groups_outlined,
      menuPurple,
      'OVANIE Pro',
      'Des solutions dédiées aux entreprises et professionnels : devis, commandes récurrentes et comptes personnalisés.',
    ),
  ];
  static const _values = <(IconData, Color, String, String)>[
    (
      Icons.description_outlined,
      menuOrange,
      'Clarté',
      'Des informations claires, des prix transparents et des conditions compréhensibles pour des décisions en toute confiance.',
    ),
    (
      Icons.verified_user_outlined,
      menuAzure,
      'Fiabilité',
      'Des partenaires vérifiés, des produits de qualité et un engagement fort pour la satisfaction de nos clients.',
    ),
    (
      Icons.groups_outlined,
      menuGreen,
      'Proximité',
      'Une équipe locale à l’écoute, des solutions adaptées au marché ivoirien et un accompagnement de proximité.',
    ),
  ];

  Widget _eyebrow(String caption, String title) => Padding(
    padding: const EdgeInsets.fromLTRB(4, 8, 4, 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          caption,
          style: const TextStyle(color: _accent, fontSize: 11.5, fontWeight: FontWeight.w700, letterSpacing: .8),
        ),
        const SizedBox(height: 2),
        Text(
          title,
          style: const TextStyle(color: menuBlue, fontSize: 18, height: 1.25, fontWeight: FontWeight.w800, letterSpacing: -.3),
        ),
      ],
    ),
  );

  /// Blocs côte à côte sur tablette, l'un sous l'autre sur téléphone.
  Widget _grid(List<Widget> cells, {int columns = 2}) => LayoutBuilder(
    builder: (context, box) {
      final perRow = box.maxWidth >= 560 ? columns : 1;
      final width = (box.maxWidth - 8 * (perRow - 1)) / perRow;
      return Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [for (final cell in cells) SizedBox(width: width, child: cell)],
      );
    },
  );

  Widget _audience((String, String, String) a) {
    final vendor = a.$1 == 'about_vendors';
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: vendor ? const Color(0xFFFFF8F3) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: vendor ? Mk.orange : Colors.transparent, width: 1.3),
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Image.asset('assets/images/mockups/${a.$1}.jpg', width: 104, height: 78, fit: BoxFit.cover, excludeFromSemantics: true),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  a.$2,
                  style: const TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  a.$3,
                  style: const TextStyle(color: menuMuted, fontSize: 12, height: 1.4, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _feature((IconData, Color, String, String) f) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(color: f.$2.withValues(alpha: .12), borderRadius: BorderRadius.circular(12)),
          child: Icon(f.$1, color: f.$2, size: 24),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                f.$3,
                style: const TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 3),
              Text(
                f.$4,
                style: const TextStyle(color: menuMuted, fontSize: 12, height: 1.4, fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _contactLine(IconData icon, String text, VoidCallback? onTap) => InkWell(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white, size: 17),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              text,
              style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final contact = OvanieReferenceDataStore.instance.contact;
    return MenuPage(
      title: 'À propos d’OVANIE',
      subtitle: 'Notre mission, notre vision',
      refresh: () async {},
      hasContent: true,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: AspectRatio(
              aspectRatio: 841 / 258,
              child: Image.asset(
                'assets/images/mockups/about_hero.jpg',
                fit: BoxFit.cover,
                semanticLabel: 'OVANIE Vendeur. Notre raison d’être : une expérience d’achat BTP plus fluide',
              ),
            ),
          ),
        ),
        _card(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'OVANIE est la marketplace ivoirienne dédiée aux matériaux et équipements de construction. '
                'Nous connectons acheteurs, vendeurs et professionnels autour d’une plateforme simple, fiable et performante '
                'pour acheter, vendre et livrer partout en Côte d’Ivoire.',
                style: TextStyle(color: Mk.icon, fontSize: 13, height: 1.5, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 8),
              const Text(
                'Notre mission : simplifier chaque étape du parcours BTP grâce à la technologie, '
                'la transparence et un engagement fort pour la satisfaction client.',
                style: TextStyle(color: menuBlue, fontSize: 13, height: 1.5, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.fromLTRB(8, 6, 12, 6),
                decoration: BoxDecoration(color: const Color(0xFFE9F6EE), borderRadius: BorderRadius.circular(99)),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ClipOval(
                      child: SizedBox(
                        width: 24,
                        height: 24,
                        child: Row(
                          children: [
                            Expanded(child: Container(color: const Color(0xFFFF8200))),
                            Expanded(child: Container(color: Colors.white)),
                            Expanded(child: Container(color: const Color(0xFF009E60))),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    const Flexible(
                      child: Text(
                        'Fièrement conçu en Côte d’Ivoire',
                        style: TextStyle(color: menuGreen, fontSize: 12.5, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        _eyebrow('OVANIE EN PRATIQUE', 'Une plateforme, plusieurs besoins'),
        _grid([for (final a in _audiences) _audience(a)]),
        const SizedBox(height: 10),
        _card(
          padding: EdgeInsets.zero,
          child: LayoutBuilder(
            builder: (context, box) {
              final wide = box.maxWidth >= 480;
              final text = Padding(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Pour les vendeurs & professionnels',
                      style: TextStyle(color: menuBlue, fontSize: 12.5, fontWeight: FontWeight.w600),
                    ),
                    const Text(
                      'Développer son activité',
                      style: TextStyle(color: menuBlue, fontSize: 18, height: 1.3, fontWeight: FontWeight.w800, letterSpacing: -.3),
                    ),
                    const SizedBox(height: 8),
                    for (final b in _benefits)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(Icons.check_circle_rounded, color: Mk.orange, size: 20),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                b,
                                style: const TextStyle(color: Mk.icon, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              );
              final photo = Image.asset('assets/images/mockups/about_grow.jpg', fit: BoxFit.cover, excludeFromSemantics: true);
              return wide
                  ? IntrinsicHeight(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(flex: 6, child: text),
                          Expanded(flex: 4, child: photo),
                        ],
                      ),
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AspectRatio(aspectRatio: 322 / 150, child: photo),
                        text,
                      ],
                    );
            },
          ),
        ),
        _eyebrow('CE QUE NOUS CONSTRUISONS', 'Un écosystème centré sur les besoins réels du bâtiment'),
        _grid([for (final f in _features) _feature(f)]),
        const SizedBox(height: 10),
        _eyebrow('NOS PRINCIPES', 'La confiance se construit dans chaque étape du parcours'),
        LayoutBuilder(
          builder: (context, box) {
            if (box.maxWidth >= 560) {
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (var i = 0; i < _values.length; i++) ...[
                    if (i > 0) const SizedBox(width: 8),
                    Expanded(child: _feature(_values[i])),
                  ],
                ],
              );
            }
            // Défilement horizontal : la hauteur suit le texte, quelle que soit sa taille.
            final cardWidth = box.maxWidth * .86;
            return Column(
              children: [
                NotificationListener<ScrollUpdateNotification>(
                  onNotification: (n) {
                    final page = (n.metrics.pixels / (cardWidth + 8)).round().clamp(0, _values.length - 1);
                    if (page != _principle) setState(() => _principle = page);
                    return false;
                  },
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    physics: const PageScrollPhysics(parent: BouncingScrollPhysics()),
                    child: IntrinsicHeight(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          for (var i = 0; i < _values.length; i++)
                            Padding(
                              padding: EdgeInsets.only(right: i == _values.length - 1 ? 0 : 8),
                              child: SizedBox(width: cardWidth, child: _feature(_values[i])),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    for (var i = 0; i < _values.length; i++)
                      Container(
                        width: 7,
                        height: 7,
                        margin: const EdgeInsets.symmetric(horizontal: 4),
                        decoration: BoxDecoration(
                          color: i == _principle ? Mk.orange : const Color(0xFFCBD3E1),
                          shape: BoxShape.circle,
                        ),
                      ),
                  ],
                ),
              ],
            );
          },
        ),
        const SizedBox(height: 12),
        Container(
          margin: const EdgeInsets.only(bottom: 10),
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            gradient: const LinearGradient(colors: [Color(0xFF0A2A66), Color(0xFF123E8C)]),
          ),
          child: Stack(
            children: [
              Positioned(
                top: 0,
                right: 0,
                width: 150,
                child: ExcludeSemantics(
                  child: ShaderMask(
                    blendMode: BlendMode.dstIn,
                    shaderCallback: (rect) =>
                        const LinearGradient(colors: [Colors.transparent, Colors.white], stops: [0, .45]).createShader(rect),
                    child: Image.asset('assets/images/mockups/about_cta.jpg', fit: BoxFit.fitWidth),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.only(right: 96),
                      child: Text(
                        'Vous souhaitez travailler avec OVANIE ?',
                        style: TextStyle(color: Colors.white, fontSize: 17, height: 1.25, fontWeight: FontWeight.w800, letterSpacing: -.2),
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Padding(
                      padding: EdgeInsets.only(right: 96),
                      child: Text(
                        'Rejoignez notre écosystème et construisons ensemble l’avenir du BTP en Côte d’Ivoire.',
                        style: TextStyle(color: Color(0xFFDCE6F8), fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                      ),
                    ),
                    const SizedBox(height: 10),
                    if (contact.hours.isNotEmpty)
                      Container(
                        margin: const EdgeInsets.only(bottom: 6),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                          color: const Color(0x26FFFFFF),
                          borderRadius: BorderRadius.circular(99),
                          border: Border.all(color: const Color(0x66FFFFFF)),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.headset_mic_outlined, color: Colors.white, size: 16),
                            const SizedBox(width: 6),
                            Flexible(
                              child: Text(
                                contact.hours,
                                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      ),
                    _contactLine(Icons.call_rounded, contact.phoneDisplay, () => _call(context, contact.telUrl)),
                    _contactLine(Icons.mail_rounded, contact.email, () => _copyEmail(context, contact.email)),
                    if (contact.address.isNotEmpty) _contactLine(Icons.location_on_rounded, contact.address, null),
                    const SizedBox(height: 10),
                    Semantics(
                      button: true,
                      label: 'Contacter OVANIE',
                      excludeSemantics: true,
                      child: Material(
                        color: Mk.orange,
                        borderRadius: BorderRadius.circular(10),
                        clipBehavior: Clip.antiAlias,
                        child: InkWell(
                          onTap: () => _openSupport(context),
                          child: const SizedBox(
                            height: 46,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(
                                  'Contacter OVANIE',
                                  style: TextStyle(color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.w700),
                                ),
                                SizedBox(width: 10),
                                Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 20),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        Center(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Text(
              'OVANIE Vendeur · Version ${widget.version}',
              style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ),
        ),
      ],
    );
  }
}
