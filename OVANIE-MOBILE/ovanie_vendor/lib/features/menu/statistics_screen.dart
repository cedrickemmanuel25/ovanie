import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../products/product_detail_screen.dart';
import 'menu_ui.dart';

class StatisticsScreen extends StatefulWidget {
  const StatisticsScreen({super.key});
  @override
  State<StatisticsScreen> createState() => _StatsState();
}

class _StatsState extends State<StatisticsScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;
  String? error;
  int period = 1;
  bool orders = false;
  DateTimeRange? custom;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    final now = DateTime.now();
    final from = period == 0
        ? now.subtract(const Duration(days: 6))
        : period == 1
        ? now.subtract(const Duration(days: 29))
        : period == 2
        ? DateTime(now.year, now.month, 1)
        : custom!.start;
    try {
      data = await menuApi(
        'statistics',
        query: {
          'from': from.toIso8601String().substring(0, 10),
          'to': (period == 3 ? custom!.end : now).toIso8601String().substring(
            0,
            10,
          ),
        },
      );
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
      );
      if (r == null || !mounted) return;
      custom = r;
    }
    period = i;
    await load();
  }

  @override
  Widget build(BuildContext context) {
    final series = rowsOf(data['series']), top = rowsOf(data['top_products']);
    final statuses = mapOf(data['statuses']);
    final growth = mapOf(data['growth']);
    final total = number(data['orders']);
    return MenuPage(
      title: 'Statistiques',
      subtitle: 'Analysez les performances de votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        menuFilters(
          ['7 jours', '30 jours', 'Ce mois', 'Personnalisé'],
          period,
          select,
        ),
        MenuMetrics(
          items: [
            (
              'Chiffre d’affaires',
              screenMoney(data['revenue']),
              Icons.trending_up,
              Colors.blue,
            ),
            (
              'Commandes',
              screenText(data['orders']),
              Icons.shopping_bag_outlined,
              menuOrange,
            ),
            (
              'Panier moyen',
              screenMoney(data['average']),
              Icons.shopping_cart_outlined,
              Colors.deepPurple,
            ),
            (
              'Taux de conversion',
              data['conversion'] == null
                  ? 'Indisponible'
                  : '${data['conversion']} %',
              Icons.track_changes,
              Colors.green,
            ),
          ],
        ),
        DataCard(
          title: 'Évolution des ventes',
          trailing: DropdownButton<bool>(
            value: orders,
            underline: const SizedBox.shrink(),
            items: const [
              DropdownMenuItem(
                value: false,
                child: Text('CA (FCFA)', style: TextStyle(fontSize: 12)),
              ),
              DropdownMenuItem(
                value: true,
                child: Text('Commandes', style: TextStyle(fontSize: 12)),
              ),
            ],
            onChanged: (v) => setState(() => orders = v!),
          ),
          child: series.isEmpty
              ? menuEmpty('Aucune donnée sur cette période')
              : Column(
                  children: [
                    SizedBox(
                      height: 190,
                      child: CustomPaint(
                        painter: _SalesPainter(
                          series
                              .map(
                                (r) => number(r[orders ? 'orders' : 'amount']),
                              )
                              .toList(),
                        ),
                        child: const SizedBox.expand(),
                      ),
                    ),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          '${series.first['date']}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: menuMuted,
                          ),
                        ),
                        Text(
                          '${series.last['date']}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: menuMuted,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
        ),
        DataCard(
          title: 'Répartition des commandes',
          child: LayoutBuilder(
            builder: (context, c) => Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final s in statuses.entries)
                  SizedBox(
                    width: (c.maxWidth - 8) / 2,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          caseStatus(s.key),
                          style: const TextStyle(color: menuBlue),
                        ),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              '${s.value}',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 20,
                              ),
                            ),
                            Text(
                              '${total > 0 ? (number(s.value) / total * 100).toStringAsFixed(1) : 0} %',
                            ),
                          ],
                        ),
                        LinearProgressIndicator(
                          value: total > 0
                              ? (number(s.value) / total).clamp(0, 1)
                              : 0,
                          color: caseColor(s.key),
                          backgroundColor: const Color(0xFFE8EFFB),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
        DataCard(
          title: 'Top produits',
          child: top.isEmpty
              ? menuEmpty('Aucune vente sur cette période')
              : Column(
                  children: top
                      .map(
                        (p) => InkWell(
                          onTap: p['id'] == null
                              ? null
                              : () => openMenuPage(
                                  context,
                                  ProductDetailScreen(
                                    productId: number(p['id']).toInt(),
                                  ),
                                ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: Row(
                              children: [
                                menuImage(p['image_url'], size: 48),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      menuHeading(screenText(p['name'])),
                                      Text(
                                        '${p['quantity']} unités vendues',
                                        style: const TextStyle(
                                          color: menuMuted,
                                          fontSize: 12,
                                        ),
                                      ),
                                      Text(
                                        screenMoney(p['amount']),
                                        style: const TextStyle(
                                          color: menuOrange,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const Icon(
                                  Icons.chevron_right,
                                  color: menuBlue,
                                ),
                              ],
                            ),
                          ),
                        ),
                      )
                      .toList(),
                ),
        ),
        DataCard(
          title: 'Analyse rapide',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final e in {
                'revenue': 'Chiffre d’affaires',
                'orders': 'Commandes',
                'average': 'Panier moyen',
              }.entries)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 5),
                  child: Text(
                    growth[e.key] == null
                        ? '${e.value} : comparaison indisponible (période précédente sans valeur).'
                        : '${e.value} : ${number(growth[e.key]) >= 0 ? '+' : ''}${growth[e.key]} % par rapport à la période précédente.',
                    style: const TextStyle(color: menuMuted),
                  ),
                ),
              if (top.isNotEmpty)
                Text(
                  'Produit le plus vendu en valeur : ${top.first['name']}.',
                  style: const TextStyle(color: menuBlue),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SalesPainter extends CustomPainter {
  _SalesPainter(this.values);
  final List<double> values;
  @override
  void paint(Canvas canvas, Size size) {
    if (values.isEmpty) return;
    final maxValue = math.max(1.0, values.reduce(math.max));
    const left = 45.0;
    final w = size.width - left - 8, h = size.height - 20;
    final paint = Paint()
      ..color = const Color(0xFFE1E9F7)
      ..strokeWidth = 1;
    for (var i = 0; i <= 4; i++) {
      final y = 10 + h * i / 4;
      canvas.drawLine(Offset(left, y), Offset(size.width, y), paint);
      final n = maxValue * (4 - i) / 4;
      final t = TextPainter(
        text: TextSpan(
          text: n >= 1000000
              ? '${(n / 1000000).toStringAsFixed(1)} M'
              : n >= 1000
              ? '${(n / 1000).toStringAsFixed(0)} K'
              : n.toStringAsFixed(0),
          style: const TextStyle(color: menuMuted, fontSize: 10),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      t.paint(canvas, Offset(0, y - 5));
    }
    final path = Path();
    final points = <Offset>[];
    for (var i = 0; i < values.length; i++) {
      final p = Offset(
        left + w * i / math.max(1, values.length - 1),
        10 + h * (1 - values[i] / maxValue),
      );
      points.add(p);
      if (i == 0) {
        path.moveTo(p.dx, p.dy);
      } else {
        path.lineTo(p.dx, p.dy);
      }
    }
    final area = Path.from(path)
      ..lineTo(points.last.dx, 10 + h)
      ..lineTo(left, 10 + h)
      ..close();
    canvas.drawPath(area, Paint()..color = Colors.blue.withValues(alpha: .09));
    canvas.drawPath(
      path,
      Paint()
        ..color = const Color(0xFF005BFF)
        ..strokeWidth = 2
        ..style = PaintingStyle.stroke,
    );
    for (final p in points) {
      canvas.drawCircle(p, 2.5, Paint()..color = const Color(0xFF005BFF));
    }
  }

  @override
  bool shouldRepaint(covariant _SalesPainter old) => old.values != values;
}
