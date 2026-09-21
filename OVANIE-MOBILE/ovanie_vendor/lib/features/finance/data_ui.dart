import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../core/network/api_client.dart';
import '../orders/order_ui.dart';

String screenText(Object? value, [String fallback = '—']) =>
    '${value ?? ''}'.trim().isEmpty ? fallback : '$value';
String screenMoney(Object? value) => value == null ? '—' : orderMoney(value);
Widget screenTitle(String title) => Text(
  title,
  style: const TextStyle(
    color: orderText,
    fontSize: 15,
    fontWeight: FontWeight.w700,
  ),
);

class VendorDataPage extends StatelessWidget {
  const VendorDataPage({
    super.key,
    required this.title,
    required this.subtitle,
    required this.child,
    required this.onRefresh,
    this.loading = false,
    this.error,
    this.back = false,
    this.bottomBar,
  });
  final String title, subtitle;
  final Widget child;
  final Future<void> Function() onRefresh;
  final bool loading, back;
  final String? error;
  final Widget? bottomBar;
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.white,
    bottomNavigationBar: bottomBar,
    body: RefreshIndicator(
      onRefresh: onRefresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: OrderHeader(
              title: title,
              subtitle: subtitle,
              showBack: back,
            ),
          ),
          if (loading)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: CircularProgressIndicator(color: orderOrange),
              ),
            )
          else
            SliverToBoxAdapter(
              child: ColoredBox(
                color: const Color(0xFF031D47),
                child: OrderSheet(
                  child: OrderResponsiveBody(
                    padding: const EdgeInsets.fromLTRB(14, 8, 14, 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (error != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 14),
                            child: Column(
                              children: [
                                Text(
                                  error!,
                                  style: const TextStyle(
                                    color: Color(0xFFB42318),
                                  ),
                                ),
                                TextButton(
                                  onPressed: onRefresh,
                                  child: const Text('Réessayer'),
                                ),
                              ],
                            ),
                          ),
                        child,
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

class DataCard extends StatelessWidget {
  const DataCard({
    super.key,
    required this.child,
    this.title,
    this.icon,
    this.trailing,
    this.color = Colors.white,
  });
  final Widget child;
  final String? title;
  final IconData? icon;
  final Widget? trailing;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: const Color(0xFFE1EAF7)),
      boxShadow: const [
        BoxShadow(
          color: Color(0x05062962),
          blurRadius: 8,
          offset: Offset(0, 3),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (title != null)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: Color(0xFFEAF0F9))),
            ),
            child: Row(
              children: [
                if (icon != null) ...[
                  Icon(icon, color: orderText, size: 23),
                  const SizedBox(width: 9),
                ],
                Expanded(child: screenTitle(title!)),
                if (trailing != null) trailing!,
              ],
            ),
          ),
        Padding(padding: const EdgeInsets.all(9), child: child),
      ],
    ),
  );
}

Widget dataPair(Widget left, Widget right, {double breakpoint = 350}) =>
    LayoutBuilder(
      builder: (context, c) =>
          c.maxWidth < breakpoint ||
              MediaQuery.textScalerOf(context).scale(12) > 16
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [left, const SizedBox(height: 8), right],
            )
          : Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: left),
                const SizedBox(width: 16),
                Expanded(child: right),
              ],
            ),
    );

Widget dataLine(
  String label,
  Object? value, {
  bool money = false,
  Color color = orderText,
  bool strong = false,
}) => Padding(
  padding: const EdgeInsets.symmetric(vertical: 3),
  child: Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Expanded(
        child: Text(
          label,
          style: const TextStyle(color: orderMuted, fontSize: 12),
        ),
      ),
      const SizedBox(width: 12),
      Flexible(
        child: Text(
          money ? screenMoney(value) : screenText(value),
          textAlign: TextAlign.right,
          style: TextStyle(
            color: color,
            fontSize: strong ? 16 : 13,
            fontWeight: strong ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
      ),
    ],
  ),
);

Widget dataInfoRow(IconData icon, String label, String value) => Padding(
  padding: const EdgeInsets.symmetric(vertical: 6),
  child: Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 16, color: orderMuted),
      const SizedBox(width: 8),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(color: orderMuted, fontSize: 11)),
            const SizedBox(height: 2),
            Text(
              value,
              style: const TextStyle(
                color: orderText,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    ],
  ),
);

Widget dataButton(
  String label,
  IconData icon,
  VoidCallback? onTap, {
  bool primary = false,
}) {
  final child = Column(
    mainAxisAlignment: MainAxisAlignment.center,
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 18),
      const SizedBox(height: 4),
      Text(
        label,
        textAlign: TextAlign.center,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontSize: 10, height: 1.1),
      ),
    ],
  );
  return primary
      ? FilledButton(
          onPressed: onTap,
          style: FilledButton.styleFrom(
            backgroundColor: orderOrange,
            minimumSize: const Size(0, 58),
            padding: const EdgeInsets.symmetric(horizontal: 4),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(9),
            ),
          ),
          child: child,
        )
      : OutlinedButton(
          onPressed: onTap,
          style: OutlinedButton.styleFrom(
            foregroundColor: orderText,
            minimumSize: const Size(0, 58),
            padding: const EdgeInsets.symmetric(horizontal: 4),
            side: const BorderSide(color: Color(0xFFA7BCE5)),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(9),
            ),
          ),
          child: child,
        );
}

Widget dataMetric(
  String label,
  Object? value,
  IconData icon, {
  Color color = orderBlue,
  Color? valueColor,
  String? suffix,
}) => Column(
  crossAxisAlignment: CrossAxisAlignment.start,
  children: [
    Container(
      width: 26,
      height: 26,
      decoration: BoxDecoration(
        color: color.withValues(alpha: .09),
        shape: BoxShape.circle,
      ),
      child: Icon(icon, size: 15, color: color),
    ),
    const SizedBox(height: 7),
    Text(
      label,
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
      style: const TextStyle(color: orderText, fontSize: 9.5, height: 1.15),
    ),
    const SizedBox(height: 4),
    Text(
      screenText(value),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: valueColor ?? orderText,
        fontSize: 12.5,
        fontWeight: FontWeight.w800,
      ),
    ),
    if (suffix != null)
      Text(
        suffix,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(color: orderMuted, fontSize: 9),
      ),
  ],
);

Widget dataMetrics(List<Widget> cells) => Row(
  children: [
    for (var i = 0; i < cells.length; i++) ...[
      if (i != 0) const SizedBox(width: 8),
      Expanded(child: cells[i]),
    ],
  ],
);

Future<void> openVendorUrl(BuildContext context, String url) async {
  try {
    if (await const MethodChannel(
          'ovanie/external_url',
        ).invokeMethod<bool>('openUrl', {'url': url}) ==
        true) {
      return;
    }
  } catch (_) {
    /* Plateforme sans composeur. */
  }
  if (!context.mounted) return;
  await showModalBottomSheet<void>(
    context: context,
    builder: (context) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SelectableText(url.replaceFirst('tel:', '')),
            const SizedBox(height: 12),
            dataButton('Copier', Icons.copy, () async {
              await Clipboard.setData(ClipboardData(text: url));
              if (context.mounted) Navigator.pop(context);
            }),
          ],
        ),
      ),
    ),
  );
}

Widget dataSupport(BuildContext context) => DataCard(
  color: orderSoftBlue,
  child: dataPair(
    const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Besoin d’aide ?',
          style: TextStyle(
            color: orderText,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
        SizedBox(height: 4),
        Text(
          'Notre équipe support est à votre écoute.',
          style: TextStyle(color: orderMuted, fontSize: 12),
        ),
      ],
    ),
    dataButton(
      'Contacter le support',
      Icons.headset_mic_outlined,
      () => openVendorUrl(context, 'tel:0161781818'),
    ),
  ),
);

String csvCell(Object? value) {
  var text = '${value ?? ''}';
  if (RegExp(r'^[=+@\-\t\r]').hasMatch(text)) text = "'$text";
  return '"${text.replaceAll('"', '""')}"';
}

Future<void> exportData(
  BuildContext context,
  String filename,
  List<List<Object?>> rows,
) async {
  try {
    final bytes = Uint8List.fromList(
      utf8.encode(
        '\ufeff${rows.map((r) => r.map(csvCell).join(';')).join('\r\n')}',
      ),
    );
    final path = await FilePicker.saveFile(fileName: filename, bytes: bytes);
    if (path != null && context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Fichier enregistré.')));
    }
  } catch (e) {
    if (context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(e))));
    }
  }
}

Future<void> downloadReceipt(BuildContext context, int id) async {
  try {
    final response = await ApiClient.dio.get<List<int>>(
      '/mobile/v1/vendor/payouts/$id/receipt',
      options: Options(responseType: ResponseType.bytes),
    );
    ApiClient.ensureSuccess(response);
    final type = response.headers.value('content-type') ?? '';
    final extension = type.contains('pdf')
        ? 'pdf'
        : type.contains('png')
        ? 'png'
        : type.contains('jpeg')
        ? 'jpg'
        : 'bin';
    final path = await FilePicker.saveFile(
      fileName: 'recu-versement-$id.$extension',
      bytes: Uint8List.fromList(response.data ?? []),
    );
    if (path != null && context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Reçu enregistré.')));
    }
  } catch (e) {
    if (context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(e))));
    }
  }
}