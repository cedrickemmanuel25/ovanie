import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import 'menu_ui.dart';

class ReviewsScreen extends StatefulWidget {
  const ReviewsScreen({super.key});
  @override
  State<ReviewsScreen> createState() => _ReviewsState();
}

class _ReviewsState extends State<ReviewsScreen> {
  Map<String, dynamic> data = {};
  bool loading = true, busy = false;
  String? error;
  String query = '';
  int filter = 0;
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
    try {
      data = await menuApi('reviews');
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> reply(Map<String, dynamic> r) async {
    final c = TextEditingController(text: '${r['reply'] ?? ''}');
    final text = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Votre réponse'),
        content: TextField(
          controller: c,
          maxLines: 5,
          maxLength: 2000,
          decoration: const InputDecoration(hintText: 'Répondre au client'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, c.text.trim()),
            child: const Text('Envoyer'),
          ),
        ],
      ),
    );
    if (text == null || text.isEmpty || !mounted) return;
    setState(() => busy = true);
    try {
      await menuApi(
        'reviews/${r['id']}/reply',
        method: 'POST',
        data: {'reply': text},
      );
      await load();
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget stars(dynamic rating) => Wrap(
    children: List.generate(
      5,
      (i) => Icon(
        i < number(rating) ? Icons.star : Icons.star_border,
        color: Colors.amber,
        size: 19,
      ),
    ),
  );
  @override
  Widget build(BuildContext context) {
    final s = mapOf(data['summary']), d = mapOf(s['distribution']);
    final count = number(s['count']);
    final rows = rowsOf(data['data'])
        .where(
          (r) =>
              '${r['comment']} ${r['client_name']} ${r['product_name']}'
                  .toLowerCase()
                  .contains(query.toLowerCase()) &&
              (filter == 0 ||
                  filter == 1 && number(r['rating']) == 5 ||
                  filter == 2 && number(r['rating']) == 4 ||
                  filter == 3 && screenText(r['comment'], '').isNotEmpty ||
                  filter == 4 && screenText(r['reply'], '').isEmpty),
        )
        .toList();
    return MenuPage(
      title: 'Avis clients',
      subtitle: 'Consultez les avis laissés sur votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        DataCard(
          child: dataPair(
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Note moyenne', style: TextStyle(color: menuBlue)),
                Text(
                  count > 0
                      ? '${number(s['average']).toStringAsFixed(1)} /5'
                      : '— /5',
                  style: const TextStyle(
                    color: menuBlue,
                    fontSize: 38,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                stars(s['average']),
                menuHeading('${s['count'] ?? 0} avis'),
              ],
            ),
            Column(
              children: [
                for (var i = 5; i >= 1; i--)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 5),
                    child: Row(
                      children: [
                        Text(
                          '$i étoiles',
                          style: const TextStyle(
                            color: menuMuted,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: LinearProgressIndicator(
                            value: count > 0 ? number(d['$i']) / count : 0,
                            color: menuOrange,
                            backgroundColor: const Color(0xFFEEF1F7),
                            minHeight: 6,
                            borderRadius: BorderRadius.circular(5),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '${d['$i'] ?? 0}',
                          style: const TextStyle(color: menuBlue),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
        MenuMetrics(
          items: [
            (
              'Réponses envoyées',
              '${s['replied'] ?? 0}',
              Icons.forum_outlined,
              menuBlue,
            ),
            (
              'Avis ce mois',
              '${s['this_month'] ?? 0}',
              Icons.star_border,
              menuOrange,
            ),
            (
              'Taux de réponse',
              count == 0
                  ? '—'
                  : '${(number(s['replied']) / count * 100).round()} %',
              Icons.task_alt,
              menuBlue,
            ),
          ],
        ),
        menuSearch((v) => setState(() => query = v), 'Rechercher un avis…'),
        menuFilters(
          [
            'Tous',
            '5 étoiles',
            '4 étoiles',
            'Avec commentaire',
            'Non répondus',
          ],
          filter,
          (v) => setState(() => filter = v),
        ),
        if (rows.isEmpty) menuEmpty('Aucun avis correspondant'),
        for (final r in rows)
          DataCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      backgroundColor: const Color(0xFFE8EDFF),
                      child: Text(
                        screenText(r['client_name'], '?').substring(0, 1),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(child: menuHeading(screenText(r['client_name']))),
                  ],
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  children: [
                    stars(r['rating']),
                    Text(
                      dateLabel(r['created_at']),
                      style: const TextStyle(fontSize: 12, color: menuMuted),
                    ),
                  ],
                ),
                Text(
                  screenText(r['product_name']),
                  style: const TextStyle(color: menuMuted, fontSize: 12),
                ),
                const SizedBox(height: 8),
                Text(
                  screenText(r['comment'], 'Sans commentaire'),
                  style: const TextStyle(color: menuBlue),
                ),
                if (screenText(r['reply'], '').isNotEmpty) ...[
                  const SizedBox(height: 8),
                  DataCard(
                    color: const Color(0xFFF3F6FE),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Votre réponse • ${dateLabel(r['replied_at'])}',
                          style: const TextStyle(
                            color: menuMuted,
                            fontSize: 12,
                          ),
                        ),
                        Text(
                          '${r['reply']}',
                          style: const TextStyle(color: menuBlue),
                        ),
                      ],
                    ),
                  ),
                ],
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: busy ? null : () => reply(r),
                    child: Text(
                      screenText(r['reply'], '').isEmpty
                          ? 'Répondre'
                          : 'Modifier la réponse',
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
