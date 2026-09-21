import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../core/utils/formatters.dart';
import '../../core/network/api_client.dart';
import '../orders/order_ui.dart';
import '../finance/data_ui.dart';
import '../shell/vendor_tabs.dart';
export '../orders/order_ui.dart'
    show orderMoney, orderText, orderMuted, orderOrange, orderGreen, orderBorder, orderNavy, OrderHeader, OrderSheet, OrderResponsiveBody;
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
const menuBlue = Color(0xFF07136B),
    menuMuted = Color(0xFF536A9C),
    menuOrange = Color(0xFFFF5700);
Widget menuHeading(String s) => Text(
  s,
  style: const TextStyle(
    fontSize: 17,
    fontWeight: FontWeight.w800,
    color: menuBlue,
  ),
);
Widget menuPill(String label, {Color color = menuBlue}) => Container(
  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
  decoration: BoxDecoration(
    color: color.withValues(alpha: .09),
    borderRadius: BorderRadius.circular(8),
  ),
  child: Text(label, style: TextStyle(color: color, fontSize: 12)),
);
Widget menuImage(
  dynamic url, {
  double size = 64,
  bool circle = false,
}) => ClipRRect(
  borderRadius: BorderRadius.circular(circle ? 100 : 10),
  child: SizedBox(
    width: size,
    height: size,
    child: '${url ?? ''}'.isEmpty
        ? ColoredBox(
            color: const Color(0xFFEAF1FC),
            child: Icon(
              circle ? Icons.storefront_outlined : Icons.inventory_2_outlined,
              color: menuBlue,
              size: 30,
            ),
          )
        : Image.network(
            '$url',
            fit: BoxFit.cover,
            errorBuilder: (_, e, s) => const ColoredBox(
              color: Color(0xFFEAF1FC),
              child: Icon(Icons.image_not_supported_outlined, color: menuMuted),
            ),
          ),
  ),
);
Widget menuIcon(IconData icon, {Color color = menuBlue}) => CircleAvatar(
  radius: 23,
  backgroundColor: color.withValues(alpha: .07),
  child: Icon(icon, color: color, size: 27),
);

class MenuPage extends StatelessWidget {
  const MenuPage({
    super.key,
    required this.title,
    required this.subtitle,
    required this.children,
    required this.refresh,
    this.loading = false,
    this.error,
    this.root = false,
    this.headerBottom,
    this.titleTrailing,
  });
  final String title, subtitle;
  final List<Widget> children;
  final Future<void> Function() refresh;
  final bool loading, root;
  final String? error;
  final Widget? headerBottom;
  final Widget? titleTrailing;
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.white,
    bottomNavigationBar: root
        ? null
        : const VendorBottomBar(selected: VendorTab.menu),
    body: RefreshIndicator(
      onRefresh: refresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: OrderHeader(
              title: title,
              subtitle: subtitle,
              showBack: !root,
              showNotifications: root,
              bottom: headerBottom,
              titleTrailing: titleTrailing,
            ),
          ),
          if (loading)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(child: CircularProgressIndicator(color: orderOrange)),
            )
          else
            SliverToBoxAdapter(
              child: ColoredBox(
                color: const Color(0xFF031D47),
                child: OrderSheet(
                  child: OrderResponsiveBody(
                    padding: const EdgeInsets.fromLTRB(14, 18, 14, 20),
                    child: ListView(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      children: [
                        if (error != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 14),
                            child: Column(
                              children: [
                                Text(error!, style: const TextStyle(color: Color(0xFFB42318))),
                                TextButton(onPressed: refresh, child: const Text('Réessayer')),
                              ],
                            ),
                          ),
                        ...children,
                      ],
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    ),
  );
}

class ShopIdentity extends StatelessWidget {
  const ShopIdentity({
    super.key,
    required this.shop,
    this.user = const {},
    this.onTap,
    this.action,
  });
  final Map<String, dynamic> shop, user;
  final VoidCallback? onTap;
  final Widget? action;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      boxShadow: const [BoxShadow(color: Color(0x1A02173D), blurRadius: 18, offset: Offset(0, 8))],
    ),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Row(
        children: [
          menuImage(shop['logo_url'], circle: true, size: 58),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  screenText(shop['name'], 'Ma boutique'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: menuBlue, fontSize: 16.5, fontWeight: FontWeight.w800),
                ),
                Text(
                  screenText(shop['owner_name'] ?? user['name']),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: menuMuted, fontSize: 13),
                ),
                if (['approved', 'verified', 'validated'].contains(shop['kyc_status'])) ...[
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: const Color(0xFFEAF8EC), borderRadius: BorderRadius.circular(8)),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.check_circle, color: orderGreen, size: 13),
                        SizedBox(width: 4),
                        Text('Vendeur vérifié', style: TextStyle(color: orderGreen, fontSize: 11.5, fontWeight: FontWeight.w700)),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (action != null)
            action!
          else if (onTap != null)
            const Icon(Icons.chevron_right, color: menuBlue),
        ],
      ),
    ),
  );
}

class MenuMetrics extends StatelessWidget {
  const MenuMetrics({super.key, required this.items});
  final List<(String, String, IconData, Color)> items;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(
      children: [
        for (var i = 0; i < items.length; i++) ...[
          if (i != 0) const SizedBox(width: 8),
          Expanded(child: _cell(items[i])),
        ],
      ],
    ),
  );

  Widget _cell((String, String, IconData, Color) i) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: orderBorder),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 34,
          height: 34,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: i.$4.withValues(alpha: .11), shape: BoxShape.circle),
          child: Icon(i.$3, color: i.$4, size: 18),
        ),
        const SizedBox(height: 9),
        Text(
          i.$1,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          softWrap: false,
          style: const TextStyle(color: menuMuted, fontSize: 10, height: 1.15),
        ),
        const SizedBox(height: 4),
        Text(
          i.$2,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

Widget menuSearch(ValueChanged<String> change, String hint) => Padding(
  padding: const EdgeInsets.symmetric(vertical: 8),
  child: TextField(
    onChanged: change,
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: const Icon(Icons.search),
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFDCE5F5)),
      ),
    ),
  ),
);
Widget menuFilters(
  List<String> labels,
  int selected,
  ValueChanged<int> change, {
  List<IconData?>? icons,
}) => Padding(
  padding: const EdgeInsets.only(bottom: 12),
  child: SizedBox(
    height: 42,
    child: ListView.separated(
      scrollDirection: Axis.horizontal,
      itemCount: labels.length,
      separatorBuilder: (_, __) => const SizedBox(width: 8),
      itemBuilder: (context, i) {
        final active = i == selected;
        return InkWell(
          borderRadius: BorderRadius.circular(99),
          onTap: () => change(i),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: active ? orderNavy : Colors.white,
              borderRadius: BorderRadius.circular(99),
              border: Border.all(color: active ? orderNavy : orderBorder),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icons != null && icons[i] != null) ...[
                  Icon(icons[i], size: 15, color: active ? Colors.white : menuMuted),
                  const SizedBox(width: 6),
                ],
                Text(
                  labels[i],
                  style: TextStyle(
                    color: active ? Colors.white : menuMuted,
                    fontSize: 13,
                    fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    ),
  ),
);
Widget menuButton(
  String label,
  VoidCallback? action, {
  IconData? icon,
  bool outlined = false,
}) => outlined
    ? OutlinedButton.icon(
        onPressed: action,
        icon: Icon(icon ?? Icons.arrow_back, size: 20),
        label: Text(label),
      )
    : FilledButton.icon(
        style: FilledButton.styleFrom(
          backgroundColor: menuOrange,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
        ),
        onPressed: action,
        icon: Icon(icon ?? Icons.save_outlined, size: 20),
        label: Text(label),
      );
Widget menuInfo(String text) => DataCard(
  color: const Color(0xFFEFF5FF),
  child: Row(
    children: [
      const Icon(Icons.info, color: Color(0xFF004BCE)),
      const SizedBox(width: 10),
      Expanded(
        child: Text(
          text,
          style: const TextStyle(color: menuMuted, fontSize: 13),
        ),
      ),
    ],
  ),
);
Widget menuEmpty(String label) => Padding(
  padding: const EdgeInsets.all(24),
  child: Text(
    label,
    textAlign: TextAlign.center,
    style: const TextStyle(color: menuMuted),
  ),
);
String caseStatus(dynamic x) =>
    const {
      'pending': 'En attente',
      'open': 'Nouveau',
      'accepted': 'Validé',
      'rejected': 'Refusé',
      'refunded': 'Remboursé',
      'resolved': 'Résolu',
      'closed': 'Clôturé',
      'in_progress': 'En cours',
      'processing': 'En cours',
    }['$x'] ??
    screenText(x);
Color caseColor(dynamic x) =>
    ['accepted', 'resolved', 'closed', 'refunded'].contains(x)
    ? Colors.green
    : ['rejected', 'open'].contains(x)
    ? Colors.red
    : menuOrange;
