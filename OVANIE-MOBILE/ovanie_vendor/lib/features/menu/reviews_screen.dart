import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/mk_dialogs.dart';
import '../products/product_detail_screen.dart';
import 'menu_ui.dart';

/// Avis clients : note moyenne, répartition, indicateurs de réponse et liste
/// des avis avec la réponse du vendeur.
class ReviewsScreen extends StatefulWidget {
  const ReviewsScreen({super.key});
  @override
  State<ReviewsScreen> createState() => _ReviewsState();
}

class _ReviewsState extends State<ReviewsScreen> {
  Map<String, dynamic> data = {};
  bool loading = true, loaded = false;
  String? error;
  String query = '';
  int filter = 0;

  static const _filters = ['Tous', '5 étoiles', '4 étoiles', 'Avec commentaire', 'Non répondus'];

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
      loaded = true;
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> reply(Map<String, dynamic> r) async {
    final text = await showMkSheet<String>(context, builder: (_) => _ReplySheet(review: r));
    if (text == null || !mounted) return;
    try {
      await menuApi('reviews/${r['id']}/reply', method: 'POST', data: {'reply': text});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Votre réponse a été publiée.')));
      await load();
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  String _decimal(double value) => value.toStringAsFixed(1).replaceAll('.', ',');

  @override
  Widget build(BuildContext context) {
    final s = mapOf(data['summary']), d = mapOf(s['distribution']);
    final count = number(s['count']);
    final replied = number(s['replied']);
    final needle = query.trim().toLowerCase();
    final rows = rowsOf(data['data']).where((r) {
      final hasComment = screenText(r['comment'], '').isNotEmpty;
      final matches = '${r['comment']} ${r['client_name']} ${r['product_name']}'.toLowerCase().contains(needle);
      return matches &&
          switch (filter) {
            1 => number(r['rating']) == 5,
            2 => number(r['rating']) == 4,
            3 => hasComment,
            4 => screenText(r['reply'], '').isEmpty,
            _ => true,
          };
    }).toList();

    return MenuPage(
      title: 'Avis clients',
      subtitle: 'Consultez les avis laissés sur votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: loaded,
      children: [
        _summaryCard(s, d, count),
        LayoutBuilder(
          builder: (context, box) {
            final width = (box.maxWidth - 16) / 3;
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                children: [
                  SizedBox(
                    width: width,
                    child: _metric(Icons.chat_bubble_outline_rounded, menuGreen, '${replied.toInt()}', 'Réponses envoyées'),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: width,
                    child: _metric(Icons.calendar_month_rounded, menuAzure, '${number(s['this_month']).toInt()}', 'Avis ce mois'),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: width,
                    child: _metric(
                      Icons.bar_chart_rounded,
                      menuOrange,
                      count == 0 ? '—' : '${(replied / count * 100).round()} %',
                      'Taux de réponse',
                    ),
                  ),
                ],
              ),
            );
          },
        ),
        menuSearch((v) => setState(() => query = v), 'Rechercher un avis…'),
        menuFilters(_filters, filter, (v) => setState(() => filter = v)),
        if (rows.isEmpty)
          menuEmpty(
            count == 0 ? 'Aucun avis pour le moment' : 'Aucun avis ne correspond',
            icon: Icons.star_border_rounded,
            hint: count == 0
                ? 'Les avis de vos clients apparaîtront ici après leurs achats.'
                : 'Modifiez la recherche ou le filtre.',
          ),
        for (final r in rows) _reviewCard(r),
      ],
    );
  }

  Widget _summaryCard(Map<String, dynamic> s, Map<String, dynamic> d, double count) {
    final average = number(s['average']);
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              flex: 9,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text('Note moyenne', style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w600)),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text.rich(
                      TextSpan(
                        text: count > 0 ? _decimal(average) : '—',
                        children: const [
                          TextSpan(text: ' /5', style: TextStyle(color: menuMuted, fontSize: 22, fontWeight: FontWeight.w600)),
                        ],
                      ),
                      style: const TextStyle(color: menuBlue, fontSize: 46, height: 1.1, fontWeight: FontWeight.w800),
                    ),
                  ),
                  _Stars(rating: average, size: 22),
                  const SizedBox(height: 4),
                  Text(
                    count > 1 ? '${count.toInt()} avis' : '${count.toInt()} avis',
                    style: const TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            ),
            const VerticalDivider(width: 20, color: orderBorder),
            Expanded(
              flex: 11,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var i = 5; i >= 1; i--)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(
                        children: [
                          SizedBox(
                            width: 56,
                            child: Text(
                              i > 1 ? '$i étoiles' : '1 étoile',
                              style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
                            ),
                          ),
                          Expanded(
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(99),
                              child: LinearProgressIndicator(
                                value: count > 0 ? number(d['$i']) / count : 0,
                                minHeight: 8,
                                color: Mk.orange,
                                backgroundColor: const Color(0xFFE9EDF4),
                              ),
                            ),
                          ),
                          SizedBox(
                            width: 30,
                            child: Text(
                              '${number(d['$i']).toInt()}',
                              textAlign: TextAlign.right,
                              style: const TextStyle(color: menuBlue, fontSize: 12.5, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _metric(IconData icon, Color color, String value, String label) => Container(
    padding: const EdgeInsets.fromLTRB(8, 10, 8, 10),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .06),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: color.withValues(alpha: .18)),
    ),
    child: Row(
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(color: color.withValues(alpha: .14), borderRadius: BorderRadius.circular(9)),
          child: Icon(icon, color: color, size: 19),
        ),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(value, style: const TextStyle(color: menuBlue, fontSize: 19, height: 1.15, fontWeight: FontWeight.w800)),
              ),
              Text(
                label,
                maxLines: 2,
                style: const TextStyle(color: menuMuted, fontSize: 11, height: 1.2, fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _reviewCard(Map<String, dynamic> r) {
    final name = screenText(r['client_name'], 'Client OVANIE');
    final comment = screenText(r['comment'], '');
    final replyText = screenText(r['reply'], '');
    final productId = int.tryParse('${r['product_id'] ?? ''}');
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(14, 14, 6, 14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Initial(name: name),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: menuBlue, fontSize: 15.5, fontWeight: FontWeight.w800),
                      ),
                    ),
                    Text(menuDate(r['created_at']), style: const TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500)),
                    PopupMenuButton<String>(
                      tooltip: 'Plus d’actions',
                      icon: const Icon(Icons.more_horiz_rounded, color: Mk.icon),
                      onSelected: (value) {
                        if (value == 'reply') reply(r);
                        if (value == 'product' && productId != null) {
                          openMenuPage(context, ProductDetailScreen(productId: productId));
                        }
                        if (value == 'copy') {
                          Clipboard.setData(ClipboardData(text: comment));
                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Commentaire copié.')));
                        }
                      },
                      itemBuilder: (_) => [
                        PopupMenuItem(value: 'reply', child: Text(replyText.isEmpty ? 'Répondre' : 'Modifier la réponse')),
                        if (productId != null) const PopupMenuItem(value: 'product', child: Text('Voir le produit')),
                        if (comment.isNotEmpty) const PopupMenuItem(value: 'copy', child: Text('Copier le commentaire')),
                      ],
                    ),
                  ],
                ),
                _Stars(rating: number(r['rating']), size: 20),
                if (screenText(r['product_name'], '').isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.fromLTRB(4, 4, 12, 4),
                    decoration: BoxDecoration(color: const Color(0xFFF2F4F8), borderRadius: BorderRadius.circular(8)),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        menuImage(r['product_image_url'], size: 34, radius: 6),
                        const SizedBox(width: 8),
                        Flexible(
                          child: Text(
                            '${r['product_name']}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Mk.icon, fontSize: 13, fontWeight: FontWeight.w500),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Text(
                  comment.isEmpty ? 'Note sans commentaire.' : comment,
                  style: TextStyle(
                    color: comment.isEmpty ? menuMuted : menuBlue,
                    fontSize: 14,
                    height: 1.4,
                    fontStyle: comment.isEmpty ? FontStyle.italic : FontStyle.normal,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 10),
                if (replyText.isNotEmpty)
                  Container(
                    margin: const EdgeInsets.only(right: 8),
                    padding: const EdgeInsets.fromLTRB(12, 10, 10, 6),
                    decoration: const BoxDecoration(
                      color: Mk.softBlue,
                      borderRadius: BorderRadius.horizontal(right: Radius.circular(10)),
                      border: Border(left: BorderSide(color: menuAzure, width: 3)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text.rich(
                          TextSpan(
                            text: 'Votre réponse',
                            style: const TextStyle(color: menuAzure, fontWeight: FontWeight.w800),
                            children: [
                              if (menuDate(r['replied_at']).isNotEmpty)
                                TextSpan(
                                  text: '  •  ${menuDate(r['replied_at'])}',
                                  style: const TextStyle(color: menuMuted, fontWeight: FontWeight.w500),
                                ),
                            ],
                          ),
                          style: const TextStyle(fontSize: 13),
                        ),
                        const SizedBox(height: 4),
                        Text(replyText, style: const TextStyle(color: menuBlue, fontSize: 14, height: 1.4, fontWeight: FontWeight.w500)),
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton.icon(
                            onPressed: () => reply(r),
                            icon: const Icon(Icons.edit_outlined, size: 17),
                            label: const Text('Modifier la réponse'),
                            style: TextButton.styleFrom(
                              foregroundColor: menuAzure,
                              textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ),
                      ],
                    ),
                  )
                else
                  Align(
                    alignment: Alignment.centerRight,
                    child: Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: Material(
                        color: Mk.orange,
                        borderRadius: BorderRadius.circular(10),
                        clipBehavior: Clip.antiAlias,
                        child: InkWell(
                          onTap: () => reply(r),
                          child: const Padding(
                            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.reply_rounded, color: Colors.white, size: 20),
                                SizedBox(width: 6),
                                Text('Répondre', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700)),
                              ],
                            ),
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
    );
  }
}

/// Étoiles pleines, demi-étoile et étoiles vides pour une note sur 5.
class _Stars extends StatelessWidget {
  const _Stars({required this.rating, this.size = 20});
  final double rating;
  final double size;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Note : ${rating.toStringAsFixed(1).replaceAll('.', ',')} sur 5',
    child: ExcludeSemantics(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 1; i <= 5; i++)
            Icon(
              rating >= i
                  ? Icons.star_rounded
                  : rating >= i - .5
                  ? Icons.star_half_rounded
                  : Icons.star_rounded,
              color: rating >= i - .5 ? const Color(0xFFF5A623) : const Color(0xFFD9DEE8),
              size: size,
            ),
        ],
      ),
    ),
  );
}

/// Rond orange avec l'initiale du client.
class _Initial extends StatelessWidget {
  const _Initial({required this.name, this.size = 48});
  final String name;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: const BoxDecoration(color: Color(0xFFFFE6D6), shape: BoxShape.circle),
    child: Text(
      name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase(),
      style: TextStyle(color: const Color(0xFFE85400), fontSize: size * .44, fontWeight: FontWeight.w800),
    ),
  );
}

/// Feuille « Votre réponse » : l'avis rappelé, le champ de réponse et l'envoi.
class _ReplySheet extends StatefulWidget {
  const _ReplySheet({required this.review});
  final Map<String, dynamic> review;
  @override
  State<_ReplySheet> createState() => _ReplySheetState();
}

class _ReplySheetState extends State<_ReplySheet> {
  late final _text = TextEditingController(text: screenText(widget.review['reply'], ''));
  String? _error;

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  void _send() {
    final text = _text.text.trim();
    if (text.length < 2) {
      setState(() => _error = 'Écrivez votre réponse avant de l’envoyer.');
      return;
    }
    Navigator.pop(context, text);
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.review;
    final name = screenText(r['client_name'], 'Client OVANIE');
    final comment = screenText(r['comment'], '');
    return MkSheetFrame(
      children: [
        const Text('Votre réponse', style: TextStyle(color: menuBlue, fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 12),
        MkSheetSummary(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _Initial(name: name, size: 44),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, style: const TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w800)),
                    _Stars(rating: number(r['rating']), size: 18),
                    const SizedBox(height: 4),
                    Text(
                      comment.isEmpty ? 'Note sans commentaire.' : comment,
                      style: const TextStyle(color: menuBlue, fontSize: 14, height: 1.4, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _text,
          autofocus: true,
          minLines: 3,
          maxLines: 6,
          textCapitalization: TextCapitalization.sentences,
          inputFormatters: [LengthLimitingTextInputFormatter(2000)],
          style: mkFieldText.copyWith(height: 1.45),
          decoration: mkInput(hint: 'Répondre au client'),
          onChanged: (_) {
            if (_error != null) setState(() => _error = null);
          },
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 6, left: 4),
            child: Text(_error!, style: const TextStyle(color: Mk.danger, fontSize: 12.5, fontWeight: FontWeight.w600)),
          ),
        const SizedBox(height: 4),
        const Text(
          'Votre réponse sera visible publiquement sous l’avis du client.',
          style: TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Annuler', style: TextStyle(color: orderBlue, fontSize: 15, fontWeight: FontWeight.w700)),
            ),
            const Spacer(),
            SizedBox(width: 150, child: MkBarButton(label: 'Envoyer', filled: true, onTap: _send)),
          ],
        ),
      ],
    );
  }
}
