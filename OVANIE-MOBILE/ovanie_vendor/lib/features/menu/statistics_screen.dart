import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../orders/order_ui.dart' show orderStatusLabel;
import '../products/product_detail_screen.dart';
import 'menu_ui.dart';

/// Statistiques de la boutique : indicateurs, évolution des ventes,
/// répartition des commandes et meilleurs produits sur la période choisie.
class StatisticsScreen extends StatefulWidget {
  const StatisticsScreen({super.key});
  @override
  State<StatisticsScreen> createState() => _StatsState();
}

class _StatsState extends State<StatisticsScreen> {
  Map<String, dynamic> data = {};
  bool loading = true, loaded = false;
  String? error;

  /// 0 : 7 jours · 1 : 30 jours · 2 : mois en cours · 3 : période personnalisée.
  int period = 1;
  bool orders = false;
  DateTimeRange? custom;

  static const _periods = ['7 jours', '30 jours', 'Ce mois', 'Personnalisé'];
  static const _statusColors = <String, Color>{
    'pending': Color(0xFFF5A623),
    'accepted': Color(0xFFF5A623),
    'preparing': Color(0xFF1668E3),
    'ready': Color(0xFF1668E3),
    'shipped': Color(0xFF6D3BD9),
    'delivered': Color(0xFF22A45D),
    'cancelled': Color(0xFFE5484D),
  };

  @override
  void initState() {
    super.initState();
    load();
  }

  String _iso(DateTime d) => d.toIso8601String().substring(0, 10);

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    final now = DateTime.now();
    final from = switch (period) {
      0 => now.subtract(const Duration(days: 6)),
      1 => now.subtract(const Duration(days: 29)),
      2 => DateTime(now.year, now.month, 1),
      _ => custom!.start,
    };
    try {
      data = await menuApi('statistics', query: {'from': _iso(from), 'to': _iso(period == 3 ? custom!.end : now)});
      loaded = true;
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> select(int i) async {
    if (i == 3) {
      final r = await showDateRangePicker(
        context: context,
        firstDate: DateTime(2020),
        lastDate: DateTime.now(),
        initialDateRange: custom,
        helpText: 'Choisir une période',
        cancelText: 'Annuler',
        confirmText: 'Valider',
        saveText: 'Valider',
      );
      if (r == null || !mounted) return;
      custom = r;
    } else if (i == period) {
      return;
    }
    period = i;
    await load();
  }

  String _percent(double value) => '${value.toStringAsFixed(1).replaceAll('.', ',')} %';

  String _shortDate(dynamic raw) {
    final date = DateTime.tryParse('${raw ?? ''}');
    if (date == null) return screenText(raw, '');
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final series = rowsOf(data['series']), top = rowsOf(data['top_products']);
    final growth = mapOf(data['growth']);
    return MenuPage(
      title: 'Statistiques',
      subtitle: 'Analysez les performances de votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: loaded,
      children: [
        _periodBar(),
        _metrics(growth),
        _salesCard(series),
        _statusCard(),
        _topCard(top),
        _insights(growth, top),
      ],
    );
  }

  // ---- Période -----------------------------------------------------------------
  Widget _periodBar() => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(4),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(99)),
    child: Row(
      children: [
        for (var i = 0; i < _periods.length; i++)
          Expanded(
            flex: i == 3 ? 5 : 4,
            child: Semantics(
              button: true,
              selected: period == i,
              child: Material(
                color: period == i ? Mk.orange : Colors.transparent,
                borderRadius: BorderRadius.circular(99),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: loading ? null : () => select(i),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 10),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        _periods[i],
                        maxLines: 1,
                        style: TextStyle(
                          color: period == i ? Colors.white : Mk.icon,
                          fontSize: 13,
                          fontWeight: period == i ? FontWeight.w700 : FontWeight.w500,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    ),
  );

  // ---- Indicateurs ----------------------------------------------------------------
  Widget _metrics(Map<String, dynamic> growth) {
    final conversion = data['conversion'];
    final cells = <Widget>[
      _metric(Icons.bar_chart_rounded, menuOrange, 'Chiffre d’affaires', orderMoney(data['revenue']), growth['revenue']),
      _metric(Icons.shopping_bag_outlined, menuAzure, 'Commandes', '${number(data['orders']).toInt()}', growth['orders']),
      _metric(Icons.shopping_cart_outlined, menuOrange, 'Panier moyen', orderMoney(data['average']), growth['average']),
      _metric(
        Icons.track_changes_rounded,
        menuAzure,
        'Taux de conversion',
        conversion == null ? 'Non mesuré' : _percent(number(conversion)),
        growth['conversion'],
        muted: conversion == null,
      ),
    ];
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: LayoutBuilder(
        builder: (context, box) {
          final width = (box.maxWidth - 8) / 2;
          return Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [for (final cell in cells) SizedBox(width: width, child: cell)],
          );
        },
      ),
    );
  }

  Widget _metric(IconData icon, Color color, String label, String value, dynamic delta, {bool muted = false}) {
    final change = delta == null ? null : number(delta);
    return Container(
      padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          menuIcon(icon, color: color, size: 40),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 2),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    value,
                    maxLines: 1,
                    style: TextStyle(
                      color: muted ? menuMuted : menuBlue,
                      fontSize: muted ? 14 : 16.5,
                      fontWeight: muted ? FontWeight.w600 : FontWeight.w800,
                    ),
                  ),
                ),
                if (change != null) ...[
                  const SizedBox(height: 4),
                  menuPill(
                    '${change >= 0 ? '+' : ''}${change.toStringAsFixed(change == change.roundToDouble() ? 0 : 1).replaceAll('.', ',')} %',
                    color: change >= 0 ? menuGreen : menuRed,
                    icon: change >= 0 ? Icons.arrow_drop_up_rounded : Icons.arrow_drop_down_rounded,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---- Évolution des ventes ------------------------------------------------------------
  Widget _salesCard(List<Map<String, dynamic>> series) {
    final values = [for (final r in series) number(r[orders ? 'orders' : 'amount'])];
    final hasSales = values.any((v) => v > 0);
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 10,
            runSpacing: 8,
            children: [
              menuHeading('Évolution des ventes'),
              Container(
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  color: const Color(0xFFF3F6FB),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: orderBorder),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [_seriesButton('CA (FCFA)', false), _seriesButton('Commandes', true)],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (!hasSales)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 30),
              child: Text(
                'Aucune vente sur cette période.',
                textAlign: TextAlign.center,
                style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
              ),
            )
          else ...[
            Semantics(
              label: orders
                  ? 'Courbe du nombre de commandes par jour sur la période.'
                  : 'Courbe du chiffre d’affaires par jour sur la période.',
              child: SizedBox(
                height: 180,
                child: CustomPaint(
                  painter: _SalesPainter(
                    values,
                    money: !orders,
                    peakLabel: orders ? '${values.reduce(math.max).toInt()} commandes' : orderMoney(values.reduce(math.max)),
                    peakCaption: _shortDate(series[values.indexOf(values.reduce(math.max))]['date']),
                  ),
                  child: const SizedBox.expand(),
                ),
              ),
            ),
            const SizedBox(height: 4),
            Padding(
              padding: const EdgeInsets.only(left: 44),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(_shortDate(series.first['date']), style: const TextStyle(fontSize: 11.5, color: menuMuted, fontWeight: FontWeight.w500)),
                  Text(_shortDate(series.last['date']), style: const TextStyle(fontSize: 11.5, color: menuMuted, fontWeight: FontWeight.w500)),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _seriesButton(String label, bool value) {
    final selected = orders == value;
    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? Mk.orange : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => setState(() => orders = value),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            child: Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : Mk.icon,
                fontSize: 12,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ---- Répartition des commandes -----------------------------------------------------
  Widget _statusCard() {
    final statuses = mapOf(data['statuses']);
    final entries = statuses.entries.where((e) => number(e.value) > 0).toList();
    final total = entries.fold<double>(0, (sum, e) => sum + number(e.value));
    Color colorOf(String key) => _statusColors[key] ?? Mk.icon;
    return DataCard(
      title: 'Répartition des commandes',
      child: entries.isEmpty
          ? const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'Aucune commande sur cette période.',
                style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
              ),
            )
          : Row(
              children: [
                SizedBox(
                  width: 108,
                  height: 108,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Positioned.fill(
                        child: CustomPaint(
                          painter: _DonutPainter([for (final e in entries) (number(e.value), colorOf(e.key))]),
                        ),
                      ),
                      Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            '${total.toInt()}',
                            style: const TextStyle(color: menuBlue, fontSize: 21, height: 1.1, fontWeight: FontWeight.w800),
                          ),
                          Text(
                            total > 1 ? 'commandes' : 'commande',
                            style: const TextStyle(color: menuMuted, fontSize: 11, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    children: [
                      for (var i = 0; i < entries.length; i++) ...[
                        if (i > 0) const Divider(height: 1, color: orderBorder),
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 7),
                          child: Row(
                            children: [
                              Container(
                                width: 10,
                                height: 10,
                                decoration: BoxDecoration(color: colorOf(entries[i].key), shape: BoxShape.circle),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  orderStatusLabel(entries[i].key),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(color: Mk.icon, fontSize: 13, fontWeight: FontWeight.w500),
                                ),
                              ),
                              Text(
                                '${number(entries[i].value).toInt()}',
                                style: const TextStyle(color: menuBlue, fontSize: 13.5, fontWeight: FontWeight.w800),
                              ),
                              SizedBox(
                                width: 54,
                                child: Text(
                                  _percent(number(entries[i].value) / total * 100),
                                  textAlign: TextAlign.right,
                                  style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  // ---- Top produits -------------------------------------------------------------------
  Widget _topCard(List<Map<String, dynamic>> top) {
    const medals = [Color(0xFFF5B301), Color(0xFF9AA5B8), Color(0xFFC77B3B)];
    final rows = top.take(5).toList();
    return DataCard(
      title: 'Top produits',
      child: rows.isEmpty
          ? const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'Aucune vente sur cette période.',
                style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
              ),
            )
          : Column(
              children: [
                for (var i = 0; i < rows.length; i++) ...[
                  if (i > 0) const Divider(height: 1, color: orderBorder),
                  InkWell(
                    onTap: rows[i]['id'] == null
                        ? null
                        : () => openMenuPage(context, ProductDetailScreen(productId: number(rows[i]['id']).toInt())),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(
                        children: [
                          Container(
                            width: 24,
                            height: 24,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(color: i < 3 ? medals[i] : Mk.softBlue, shape: BoxShape.circle),
                            child: Text(
                              '${i + 1}',
                              style: TextStyle(color: i < 3 ? Colors.white : Mk.icon, fontSize: 12, fontWeight: FontWeight.w800),
                            ),
                          ),
                          const SizedBox(width: 8),
                          menuImage(rows[i]['image_url'], size: 44),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  screenText(rows[i]['name'], 'Produit'),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(color: menuBlue, fontSize: 13.5, height: 1.25, fontWeight: FontWeight.w700),
                                ),
                                Text(
                                  '${number(rows[i]['quantity']).toInt()} ${number(rows[i]['quantity']) > 1 ? 'unités vendues' : 'unité vendue'}'
                                  ' · ${orderMoney(rows[i]['amount'])}',
                                  style: const TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
                                ),
                              ],
                            ),
                          ),
                          if (rows[i]['id'] != null) const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 22),
                        ],
                      ),
                    ),
                  ),
                ],
              ],
            ),
    );
  }

  // ---- Analyse rapide -------------------------------------------------------------------
  Widget _insights(Map<String, dynamic> growth, List<Map<String, dynamic>> top) {
    String delta(dynamic raw) {
      final value = number(raw);
      return '${value >= 0 ? '+' : ''}${value.toStringAsFixed(value == value.roundToDouble() ? 0 : 1).replaceAll('.', ',')} %';
    }

    final lines = <String>[
      if (growth['revenue'] != null) 'Chiffre d’affaires : ${delta(growth['revenue'])} par rapport à la période précédente.',
      if (growth['orders'] != null) 'Commandes : ${delta(growth['orders'])} par rapport à la période précédente.',
      if (top.isNotEmpty) 'Produit le plus vendu en valeur : ${screenText(top.first['name'], 'Produit')}.',
    ];
    if (lines.isEmpty) return const SizedBox.shrink();
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(14)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    menuIcon(Icons.lightbulb_outline_rounded, size: 34),
                    const SizedBox(width: 10),
                    menuHeading('Analyse rapide'),
                  ],
                ),
                const SizedBox(height: 8),
                for (final line in lines)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Padding(
                          padding: EdgeInsets.only(top: 6, right: 8),
                          child: Icon(Icons.circle, color: menuAzure, size: 8),
                        ),
                        Expanded(
                          child: Text(line, style: const TextStyle(color: Mk.icon, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500)),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          if (MediaQuery.sizeOf(context).width >= 380)
            Padding(
              padding: const EdgeInsets.only(left: 8),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.asset('assets/images/mockups/stats_growth.jpg', width: 84, excludeFromSemantics: true),
              ),
            ),
        ],
      ),
    );
  }
}

/// Courbe des ventes : aire orangée, graduations à gauche et bulle sur le pic.
class _SalesPainter extends CustomPainter {
  _SalesPainter(this.values, {required this.money, required this.peakLabel, required this.peakCaption});
  final List<double> values;
  final bool money;
  final String peakLabel, peakCaption;

  String _axis(double n) {
    String trim(double v) => v.toStringAsFixed(v == v.roundToDouble() ? 0 : 1).replaceAll('.', ',');
    if (n <= 0) return '0';
    if (!money) return trim(n);
    if (n >= 1000000) return '${trim(n / 1000000)} M';
    if (n >= 1000) return '${trim(n / 1000)} k';
    return trim(n);
  }

  @override
  void paint(Canvas canvas, Size size) {
    if (values.isEmpty) return;
    final peak = values.reduce(math.max);
    // Échelle arrondie au-dessus du pic.
    var step = 1.0;
    final raw = math.max(peak, 1) / 4;
    while (step * 10 <= raw) {
      step *= 10;
    }
    step = [1.0, 2.0, 2.5, 5.0, 10.0].map((f) => f * step).firstWhere((v) => v >= raw);
    if (!money) step = step.ceilToDouble();
    final top = step * 4;

    const left = 44.0, topPad = 34.0;
    final w = size.width - left - 6, h = size.height - topPad - 6;
    final grid = Paint()
      ..color = const Color(0xFFE6EBF3)
      ..strokeWidth = 1;
    for (var i = 0; i <= 4; i++) {
      final y = topPad + h * i / 4;
      canvas.drawLine(Offset(left, y), Offset(size.width, y), grid);
      final t = TextPainter(
        text: TextSpan(
          text: _axis(top * (4 - i) / 4),
          style: const TextStyle(color: Color(0xFF6B7896), fontSize: 10.5, fontFamily: Mk.font),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      t.paint(canvas, Offset(left - t.width - 8, y - t.height / 2));
    }

    final points = <Offset>[
      for (var i = 0; i < values.length; i++)
        Offset(left + w * i / math.max(1, values.length - 1), topPad + h * (1 - values[i] / top)),
    ];
    final path = Path()..moveTo(points.first.dx, points.first.dy);
    for (var i = 1; i < points.length; i++) {
      final mid = (points[i - 1].dx + points[i].dx) / 2;
      path.cubicTo(mid, points[i - 1].dy, mid, points[i].dy, points[i].dx, points[i].dy);
    }
    final area = Path.from(path)
      ..lineTo(points.last.dx, topPad + h)
      ..lineTo(points.first.dx, topPad + h)
      ..close();
    canvas.drawPath(
      area,
      Paint()
        ..shader = const LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [Color(0x40FF6A00), Color(0x05FF6A00)],
        ).createShader(Rect.fromLTWH(left, topPad, w, h)),
    );
    canvas.drawPath(
      path,
      Paint()
        ..color = const Color(0xFFFF6A00)
        ..strokeWidth = 2.4
        ..strokeCap = StrokeCap.round
        ..style = PaintingStyle.stroke,
    );

    // Pic de la période : point et bulle.
    final index = values.indexOf(peak);
    final p = points[index];
    canvas.drawCircle(p, 5.5, Paint()..color = Colors.white);
    canvas.drawCircle(p, 4, Paint()..color = const Color(0xFFFF6A00));
    final label = TextPainter(
      text: TextSpan(
        text: peakLabel,
        style: const TextStyle(color: Color(0xFF0A1B4F), fontSize: 11, fontWeight: FontWeight.w800, fontFamily: Mk.font),
        children: [
          TextSpan(
            text: '  $peakCaption',
            style: const TextStyle(color: Color(0xFF6B7896), fontSize: 10.5, fontWeight: FontWeight.w500),
          ),
        ],
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    final bubbleWidth = label.width + 16, bubbleHeight = label.height + 10;
    final bx = (p.dx - bubbleWidth / 2).clamp(left, size.width - bubbleWidth);
    final by = math.max(0.0, p.dy - bubbleHeight - 9);
    final bubble = RRect.fromRectAndRadius(Rect.fromLTWH(bx, by, bubbleWidth, bubbleHeight), const Radius.circular(7));
    canvas.drawRRect(bubble.shift(const Offset(0, 1)), Paint()..color = const Color(0x1F062962));
    canvas.drawRRect(bubble, Paint()..color = Colors.white);
    canvas.drawRRect(
      bubble,
      Paint()
        ..color = const Color(0xFFE4E9F1)
        ..style = PaintingStyle.stroke,
    );
    label.paint(canvas, Offset(bx + 8, by + 5));
  }

  @override
  bool shouldRepaint(covariant _SalesPainter old) => old.values != values || old.money != money;
}

/// Anneau de répartition : un arc par statut.
class _DonutPainter extends CustomPainter {
  _DonutPainter(this.slices);
  final List<(double, Color)> slices;

  @override
  void paint(Canvas canvas, Size size) {
    final total = slices.fold<double>(0, (sum, s) => sum + s.$1);
    if (total <= 0) return;
    const stroke = 15.0;
    final rect = Rect.fromLTWH(stroke / 2, stroke / 2, size.width - stroke, size.height - stroke);
    final gap = slices.length > 1 ? .05 : 0.0;
    var start = -math.pi / 2;
    for (final slice in slices) {
      final sweep = slice.$1 / total * math.pi * 2;
      canvas.drawArc(
        rect,
        start + gap / 2,
        math.max(.01, sweep - gap),
        false,
        Paint()
          ..color = slice.$2
          ..strokeWidth = stroke
          ..style = PaintingStyle.stroke,
      );
      start += sweep;
    }
  }

  @override
  bool shouldRepaint(covariant _DonutPainter old) => old.slices != slices;
}
