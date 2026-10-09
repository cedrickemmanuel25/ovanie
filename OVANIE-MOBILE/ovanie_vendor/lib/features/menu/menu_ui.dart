import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../core/utils/formatters.dart';
import '../../core/network/api_client.dart';
import '../orders/order_ui.dart';
import '../finance/data_ui.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../orders/order_widgets.dart';
import '../shell/vendor_tabs.dart';
export '../orders/order_ui.dart'
    show orderMoney, orderText, orderMuted, orderOrange, orderGreen, orderBorder, orderNavy, orderBlue, OrderCard, OrderSectionTitle, OrderCaption;
export '../../core/ui/vendor_mockup_ui.dart' show Mk, MkBarButton, MkInfoBox, MkNotice, MkLabel, mkInput, mkFieldText, MkTheme;
export '../finance/data_ui.dart'
    show DataCard, dataPair, dataLine, screenText, screenMoney;

Map<String, dynamic> mapOf(dynamic x) =>
    x is Map ? Map<String, dynamic>.from(x) : {};
List<Map<String, dynamic>> rowsOf(dynamic x) => x is List
    ? x.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : [];
double number(dynamic x) => double.tryParse('$x') ?? 0;
String dateLabel(dynamic x) => dateTimeLabel(x);
Future<bool> launchUrl(Uri uri) async {
  final opened = await const MethodChannel(
    'ovanie/external_url',
  ).invokeMethod<bool>('openUrl', {'url': uri.toString()});
  if (opened != true) throw Exception('Impossible d’ouvrir ce lien.');
  return true;
}

Future<Map<String, dynamic>> menuApi(
  String path, {
  String method = 'GET',
  Map<String, dynamic>? data,
  Map<String, dynamic>? query,
  Map<String, File>? files,
}) async {
  dynamic body = data;
  if (files != null) {
    body = FormData.fromMap({
      ...?data,
      for (final e in files.entries)
        e.key: await MultipartFile.fromFile(
          e.value.path,
          filename: e.value.uri.pathSegments.last,
        ),
    });
  }
  final r = await ApiClient.dio.request(
    '/mobile/v1/vendor/$path',
    data: body,
    queryParameters: query,
    options: Options(method: method),
  );
  ApiClient.ensureSuccess(r);
  return mapOf(r.data);
}

Future<List<Map<String, dynamic>>> allMenuRows(String path) async {
  final result = <Map<String, dynamic>>[];
  var page = 1;
  while (true) {
    final r = await menuApi(path, query: {'page': page, 'per_page': 50});
    result.addAll(rowsOf(r['data']));
    if (page >= number(mapOf(r['meta'])['last_page'])) break;
    page++;
  }
  return result;
}

void openMenuPage(BuildContext c, Widget page) =>
    Navigator.of(c).push(MaterialPageRoute(builder: (_) => page));
void menuError(BuildContext c, Object e) => ScaffoldMessenger.of(
  c,
).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(e))));
const menuBlue = Mk.navy, menuMuted = Mk.muted, menuOrange = Mk.orange;
const menuGreen = Color(0xFF12894A), menuRed = Color(0xFFD92D20), menuAzure = Color(0xFF1668E3), menuPurple = Color(0xFF6D3BD9);

Widget menuHeading(String s) => Text(
  s,
  style: const TextStyle(fontSize: 16, height: 1.25, fontWeight: FontWeight.w800, color: menuBlue),
);

/// Pastille de statut arrondie ; [icon] ajoute un pictogramme devant le libellé.
Widget menuPill(String label, {Color color = menuBlue, IconData? icon}) => Container(
  padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
  decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(99)),
  child: Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      if (icon != null) ...[Icon(icon, color: color, size: 16), const SizedBox(width: 5)],
      Flexible(
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(color: color, fontSize: 12.5, fontWeight: FontWeight.w700),
        ),
      ),
    ],
  ),
);

/// Pastille verte « Vendeur vérifié ».
Widget menuVerifiedPill() => menuPill('Vendeur vérifié', color: menuGreen, icon: Icons.check_circle_rounded);

bool menuShopVerified(Map<String, dynamic> shop) =>
    ['approved', 'verified', 'validated', 'automated_checked'].contains(shop['kyc_status']);

Widget menuImage(dynamic url, {double size = 64, bool circle = false, double? width, double radius = 10}) => ClipRRect(
  borderRadius: BorderRadius.circular(circle ? 100 : radius),
  child: SizedBox(
    width: width ?? size,
    height: size,
    child: '${url ?? ''}'.isEmpty
        ? ColoredBox(
            color: circle ? const Color(0xFFFFEEE3) : Mk.softBlue,
            child: Icon(
              circle ? Icons.storefront_outlined : Icons.inventory_2_outlined,
              color: circle ? menuOrange : Mk.icon,
              size: size * .46,
            ),
          )
        : Image.network(
            '$url',
            fit: BoxFit.cover,
            errorBuilder: (_, e, s) => const ColoredBox(
              color: Mk.softBlue,
              child: Icon(Icons.image_not_supported_outlined, color: menuMuted),
            ),
          ),
  ),
);

Widget menuIcon(IconData icon, {Color color = menuAzure, double size = 44}) => Container(
  width: size,
  height: size,
  decoration: BoxDecoration(color: color.withValues(alpha: .12), shape: BoxShape.circle),
  child: Icon(icon, color: color, size: size * .52),
);

/// Page d'un écran du Menu : en-tête blanc avec retour, contenu défilant,
/// barre d'actions facultative et barre d'onglets (onglet Menu actif).
class MenuPage extends StatelessWidget {
  const MenuPage({
    super.key,
    required this.title,
    required this.subtitle,
    required this.children,
    required this.refresh,
    this.loading = false,
    this.error,
    this.hasContent,
    this.actions = const <Widget>[],
    this.showLogo = false,
    this.titleTrailing,
  });
  final String title, subtitle;
  final List<Widget> children;
  final Future<void> Function() refresh;
  final bool loading, showLogo;
  final String? error;

  /// Faux tant qu'aucune donnée n'est disponible ; par défaut, le contenu est
  /// masqué pendant le chargement.
  final bool? hasContent;
  final List<Widget> actions;
  final Widget? titleTrailing;

  @override
  Widget build(BuildContext context) => OrderPage(
    tab: VendorTab.menu,
    title: title,
    subtitle: subtitle,
    trailing: titleTrailing,
    showLogo: showLogo,
    loading: loading,
    error: error,
    hasContent: hasContent ?? !loading,
    emptyMessage: 'Ces informations sont indisponibles pour le moment.',
    onRefresh: refresh,
    gap: 0,
    actions: actions,
    children: children,
  );
}

/// Carte d'identité de la boutique : logo, nom, titulaire et statut vérifié.
class ShopIdentity extends StatelessWidget {
  const ShopIdentity({super.key, required this.shop, this.user = const {}, this.onTap, this.action});
  final Map<String, dynamic> shop, user;
  final VoidCallback? onTap;
  final Widget? action;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              menuImage(shop['logo_url'], circle: true, size: 54),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      screenText(shop['name'], 'Ma boutique'),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: menuBlue, fontSize: 16.5, height: 1.2, fontWeight: FontWeight.w800),
                    ),
                    if (screenText(shop['owner_name'] ?? user['name'], '').isNotEmpty)
                      Text(
                        screenText(shop['owner_name'] ?? user['name']),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                    if (menuShopVerified(shop)) ...[const SizedBox(height: 6), menuVerifiedPill()],
                  ],
                ),
              ),
              if (action != null) action! else if (onTap != null) const Icon(Icons.chevron_right_rounded, color: Mk.icon),
            ],
          ),
        ),
      ),
    ),
  );
}

/// Rangée d'indicateurs : pictogramme, libellé et grand chiffre centrés.
class MenuMetrics extends StatelessWidget {
  const MenuMetrics({super.key, required this.items});
  final List<(String, String, IconData, Color)> items;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: LayoutBuilder(
      builder: (context, box) {
        // Quatre cartes tiennent côte à côte ; faute de place, deux par ligne.
        final perRow = items.length <= 2 || box.maxWidth / items.length >= 76 ? items.length : 2;
        final width = (box.maxWidth - 8 * (perRow - 1)) / perRow;
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [for (final item in items) SizedBox(width: width, child: _cell(item, items.length <= 2))],
        );
      },
    ),
  );

  Widget _cell((String, String, IconData, Color) i, bool wide) => Container(
    padding: EdgeInsets.symmetric(horizontal: wide ? 12 : 6, vertical: 12),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
    child: wide
        ? Row(
            children: [
              menuIcon(i.$3, color: i.$4, size: 42),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      i.$1,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
                    ),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(i.$2, style: const TextStyle(color: menuBlue, fontSize: 20, height: 1.2, fontWeight: FontWeight.w800)),
                    ),
                  ],
                ),
              ),
            ],
          )
        : Column(
            children: [
              menuIcon(i.$3, color: i.$4, size: 36),
              const SizedBox(height: 6),
              Text(
                i.$1,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
              ),
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(i.$2, style: const TextStyle(color: menuBlue, fontSize: 21, height: 1.25, fontWeight: FontWeight.w800)),
              ),
            ],
          ),
  );
}

Widget menuSearch(ValueChanged<String> change, String hint) => Padding(
  padding: const EdgeInsets.only(bottom: 10),
  child: TextField(
    onChanged: change,
    textInputAction: TextInputAction.search,
    style: const TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w500),
    decoration: InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Mk.hint, fontSize: 14, fontWeight: FontWeight.w400),
      prefixIcon: const Icon(Icons.search_rounded, color: Mk.icon, size: 22),
      filled: true,
      fillColor: Colors.white,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(vertical: 13),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: orderBorder)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: orderBorder)),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Mk.blue, width: 1.4),
      ),
    ),
  ),
);

/// Puce de filtre : bleu nuit lorsqu'elle est sélectionnée.
Widget menuChip(String label, bool active, VoidCallback onTap, {Color activeColor = Mk.navy}) => Semantics(
  button: true,
  selected: active,
  child: Material(
    color: active ? activeColor : Colors.white,
    shape: StadiumBorder(side: BorderSide(color: active ? activeColor : orderBorder, width: 1.2)),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 15),
        child: Center(
          widthFactor: 1,
          child: Text(
            label,
            style: TextStyle(
              color: active ? Colors.white : Mk.icon,
              fontSize: 13.5,
              fontWeight: active ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
        ),
      ),
    ),
  ),
);

Widget menuFilters(List<String> labels, int selected, ValueChanged<int> change, {List<IconData?>? icons}) => Padding(
  padding: const EdgeInsets.only(bottom: 10),
  child: SizedBox(
    height: 40,
    child: ListView.separated(
      scrollDirection: Axis.horizontal,
      itemCount: labels.length,
      separatorBuilder: (_, __) => const SizedBox(width: 8),
      itemBuilder: (context, i) => menuChip(labels[i], i == selected, () => change(i)),
    ),
  ),
);

Widget menuButton(String label, VoidCallback? action, {IconData? icon, bool outlined = false, Color color = Mk.orange}) =>
    MkBarButton(label: label, onTap: action, icon: icon, filled: !outlined, color: color);

Widget menuInfo(String text) => Padding(
  padding: const EdgeInsets.only(bottom: 10),
  child: MkInfoBox(icon: Icons.info_rounded, child: Text(text)),
);

Widget menuEmpty(String label, {IconData icon = Icons.inbox_outlined, String? hint}) => Container(
  margin: const EdgeInsets.only(bottom: 10),
  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 26),
  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
  child: Column(
    children: [
      Icon(icon, color: Mk.hint, size: 42),
      const SizedBox(height: 10),
      Text(
        label,
        textAlign: TextAlign.center,
        style: const TextStyle(color: menuBlue, fontSize: 15.5, fontWeight: FontWeight.w800),
      ),
      if (hint != null) ...[
        const SizedBox(height: 4),
        Text(
          hint,
          textAlign: TextAlign.center,
          style: const TextStyle(color: menuMuted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
        ),
      ],
    ],
  ),
);

/// Intertitre en capitales espacées (« LISTE DES ZONES »).
Widget menuCaption(String text) => Padding(
  padding: const EdgeInsets.fromLTRB(4, 4, 4, 10),
  child: OrderCaption(text),
);

String caseStatus(dynamic x) =>
    const {
      'pending': 'En attente',
      'open': 'Nouveau',
      'new': 'Nouveau',
      'accepted': 'Validé',
      'rejected': 'Refusé',
      'refunded': 'Remboursé',
      'resolved': 'Résolu',
      'closed': 'Clôturé',
      'in_progress': 'En cours',
      'processing': 'En cours',
      'waiting_ovanie': 'En attente OVANIE',
    }['$x'] ??
    screenText(x);
Color caseColor(dynamic x) => ['accepted', 'resolved', 'closed', 'refunded'].contains(x)
    ? menuGreen
    : ['rejected', 'open', 'new'].contains(x)
    ? menuRed
    : '$x' == 'waiting_ovanie'
    ? menuAzure
    : const Color(0xFFE85400);

/// Date courte « 11/09/2026 ».
String menuDate(dynamic raw) {
  final date = DateTime.tryParse('${raw ?? ''}')?.toLocal();
  if (date == null) return '';
  String two(int v) => v.toString().padLeft(2, '0');
  return '${two(date.day)}/${two(date.month)}/${date.year}';
}

/// Téléphone lisible « 07 08 45 12 36 » ; valeur d'origine si le format est inconnu.
String menuPhone(dynamic raw) {
  final text = '${raw ?? ''}'.trim();
  final digits = text.replaceAll(RegExp(r'\D'), '');
  final local = digits.length == 13 && digits.startsWith('225') ? digits.substring(3) : digits;
  if (local.length != 10) return text;
  return [for (var i = 0; i < 10; i += 2) local.substring(i, i + 2)].join(' ');
}

/// Image de couverture de la boutique ; à défaut, une vue de magasin générique.
class ShopCoverImage extends StatelessWidget {
  const ShopCoverImage({super.key, required this.url});
  final String url;

  static const _fallback = 'assets/images/mockups/menu_cover.jpg';

  @override
  Widget build(BuildContext context) => url.isEmpty
      ? Image.asset(_fallback, fit: BoxFit.cover, excludeFromSemantics: true)
      : Image.network(
          url,
          fit: BoxFit.cover,
          errorBuilder: (_, e, s) => Image.asset(_fallback, fit: BoxFit.cover, excludeFromSemantics: true),
        );
}

/// Bouton orange compact « Modifier ».
Widget menuEditButton(VoidCallback? onTap, {String label = 'Modifier'}) => Material(
  color: onTap == null ? const Color(0xFFFFB98A) : Mk.orange,
  borderRadius: BorderRadius.circular(10),
  clipBehavior: Clip.antiAlias,
  child: InkWell(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.edit_rounded, color: Colors.white, size: 17),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w700)),
        ],
      ),
    ),
  ),
);

/// Champ de sélection : ouvre une feuille de choix au toucher.
class MenuSelect extends StatelessWidget {
  const MenuSelect({super.key, required this.value, required this.hint, required this.onTap, this.icon, this.error = false});
  final String? value;
  final String hint;
  final VoidCallback? onTap;
  final IconData? icon;
  final bool error;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: value ?? hint,
    child: Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: error ? Mk.danger : Mk.border, width: 1.2),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 10, 12),
          child: Row(
            children: [
              if (icon != null) ...[Icon(icon, color: menuAzure, size: 20), const SizedBox(width: 10)],
              Expanded(
                child: ExcludeSemantics(
                  child: Text(
                    value ?? hint,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: value == null ? Mk.hint : menuBlue, fontSize: 14.5, fontWeight: FontWeight.w500),
                  ),
                ),
              ),
              const Icon(Icons.keyboard_arrow_down_rounded, color: Mk.icon, size: 24),
            ],
          ),
        ),
      ),
    ),
  );
}

/// Feuille de choix unique ; renvoie la clé retenue.
Future<T?> menuPick<T>(BuildContext context, String title, Map<T, String> options, T? selected) => showModalBottomSheet<T>(
  context: context,
  isScrollControlled: true,
  backgroundColor: Colors.white,
  shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
  builder: (sheet) => MkTheme(
    lightStatusBar: false,
    child: SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(sheet).height * .72),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: 10),
            Center(
              child: Container(
                width: 44,
                height: 5,
                decoration: BoxDecoration(color: const Color(0xFFD5DBE7), borderRadius: BorderRadius.circular(99)),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 8),
              child: Text(title, style: const TextStyle(color: menuBlue, fontSize: 18, fontWeight: FontWeight.w800)),
            ),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.fromLTRB(8, 0, 8, 12),
                children: [
                  for (final option in options.entries)
                    ListTile(
                      onTap: () => Navigator.pop(sheet, option.key),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      title: Text(
                        option.value,
                        style: TextStyle(
                          color: menuBlue,
                          fontSize: 15,
                          fontWeight: option.key == selected ? FontWeight.w800 : FontWeight.w500,
                        ),
                      ),
                      trailing: option.key == selected ? const Icon(Icons.check_rounded, color: Mk.orange) : null,
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  ),
);
