import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../menu/menu_ui.dart';
import '../menu/profile_screens.dart';
import '../orders/order_detail_screen.dart';

class AfterSalesScreen extends StatefulWidget {
  const AfterSalesScreen({super.key, this.disputes = false});
  final bool disputes;
  @override
  State<AfterSalesScreen> createState() => _AfterSalesState();
}

class _AfterSalesState extends State<AfterSalesScreen> {
  List<Map<String, dynamic>> rows = [];
  Map<String, dynamic> shop = {};
  bool loading = true;
  String? error;
  String query = '';
  int filter = 0;
  String get path => widget.disputes ? 'disputes' : 'returns';
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
      rows = await allMenuRows(path);
      shop = mapOf((await menuApi('shop'))['shop']);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final statuses = widget.disputes
        ? ['all', 'open', 'in_progress', 'resolved']
        : ['all', 'pending', 'accepted', 'rejected', 'refunded'];
    final labels = widget.disputes
        ? ['Tous', 'Nouveaux', 'En cours', 'Résolus']
        : ['Tous', 'En attente', 'Validés', 'Refusés', 'Remboursés'];
    final selected = rows
        .where(
          (r) =>
              '$r'.toLowerCase().contains(query.toLowerCase()) &&
              (filter == 0 || r['status'] == statuses[filter]),
        )
        .toList();
    return MenuPage(
      title: widget.disputes ? 'Litiges' : 'Retours',
      subtitle: widget.disputes
          ? 'Suivez et traitez les réclamations de vos clients'
          : 'Gérez les demandes de retour de vos clients',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        ShopIdentity(
          shop: shop,
          onTap: () => openMenuPage(context, const ShopProfileScreen()),
        ),
        MenuMetrics(
          items: [
            ('Tous', '${rows.length}', Icons.assignment_outlined, menuBlue),
            (
              widget.disputes ? 'Nouveaux' : 'En attente',
              '${rows.where((r) => r['status'] == (widget.disputes ? 'open' : 'pending')).length}',
              Icons.error_outline,
              menuOrange,
            ),
            (
              'En cours',
              '${rows.where((r) => ['accepted', 'in_progress', 'processing'].contains(r['status'])).length}',
              Icons.schedule,
              menuOrange,
            ),
            (
              'Traités',
              '${rows.where((r) => ['resolved', 'closed', 'refunded', 'rejected'].contains(r['status'])).length}',
              Icons.check_circle_outline,
              Colors.green,
            ),
          ],
        ),
        menuSearch(
          (v) => setState(() => query = v),
          widget.disputes ? 'Rechercher un litige…' : 'Rechercher un retour…',
        ),
        menuFilters(labels, filter, (v) => setState(() => filter = v)),
        menuHeading(
          widget.disputes ? 'Liste des litiges' : 'Liste des retours',
        ),
        const SizedBox(height: 10),
        if (selected.isEmpty) menuEmpty('Aucun dossier correspondant'),
        for (final r in selected)
          DataCard(
            child: InkWell(
              onTap: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => CaseDetailScreen(
                      id: number(r['id']).toInt(),
                      disputes: widget.disputes,
                    ),
                  ),
                );
                if (mounted) await load();
              },
              child: Row(
                children: [
                  menuImage(r['image_url'], size: 65),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        menuHeading(
                          screenText(
                            r['product_name'],
                            screenText(r['reference']),
                          ),
                        ),
                        Text(
                          'Commande ${screenText(r['order_number'])}',
                          style: const TextStyle(
                            color: menuMuted,
                            fontSize: 12,
                          ),
                        ),
                        Text(
                          'Client : ${screenText(r['client_name'])}',
                          style: const TextStyle(
                            color: menuMuted,
                            fontSize: 12,
                          ),
                        ),
                        Text(
                          screenText(r['reason'] ?? r['subject']),
                          style: const TextStyle(
                            color: menuMuted,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Wrap(
                          spacing: 8,
                          runSpacing: 5,
                          children: [
                            menuPill(
                              caseStatus(r['status']),
                              color: caseColor(r['status']),
                            ),
                            Text(
                              dateLabel(r['created_at']),
                              style: const TextStyle(
                                fontSize: 11,
                                color: menuMuted,
                              ),
                            ),
                            if (widget.disputes)
                              Text(
                                screenMoney(r['amount']),
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: menuBlue,
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: menuBlue),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class CaseDetailScreen extends StatefulWidget {
  const CaseDetailScreen({super.key, required this.id, this.disputes = false});
  final int id;
  final bool disputes;
  @override
  State<CaseDetailScreen> createState() => _CaseState();
}

class _CaseState extends State<CaseDetailScreen> {
  Map<String, dynamic> data = {}, shop = {};
  bool loading = true, busy = false;
  String? error;
  final response = TextEditingController();
  String get path => widget.disputes ? 'disputes' : 'returns';
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    response.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final r = await Future.wait([
        menuApi('$path/${widget.id}'),
        menuApi('shop'),
      ]);
      data = mapOf(r[0]['case']);
      shop = mapOf(r[1]['shop']);
      response.text = screenText(data['response'], '');
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> action(String action) async {
    if (action == 'respond' && response.text.trim().isEmpty) {
      menuError(context, Exception('Rédigez une solution avant de l’envoyer.'));
      return;
    }
    setState(() => busy = true);
    try {
      await menuApi(
        '$path/${widget.id}/$action',
        method: 'POST',
        data: {
          if (action == 'respond') 'response': response.text.trim(),
          if (!widget.disputes) 'vendor_response': response.text.trim(),
        },
      );
      await load();
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = mapOf(data['product']), client = mapOf(data['client']);
    final photos = (data['attachments'] as List?) ?? [];
    return MenuPage(
      title: widget.disputes ? 'Détail du litige' : 'Détail du retour',
      subtitle: 'Consultez les informations et le suivi de ce dossier',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        ShopIdentity(shop: shop),
        DataCard(
          child: Column(
            children: [
              dataLine(
                widget.disputes ? 'ID du litige' : 'ID du retour',
                screenText(data['reference']),
              ),
              InkWell(
                onTap: data['order_id'] == null
                    ? null
                    : () => openMenuPage(
                        context,
                        OrderDetailScreen(
                          orderId: number(data['order_id']).toInt(),
                        ),
                      ),
                child: dataLine('Commande', screenText(data['order_number'])),
              ),
              dataLine('Date de demande', dateLabel(data['created_at'])),
              Align(
                alignment: Alignment.centerRight,
                child: menuPill(
                  caseStatus(data['status']),
                  color: caseColor(data['status']),
                ),
              ),
            ],
          ),
        ),
        DataCard(
          child: Row(
            children: [
              menuImage(p['image_url'], size: 80),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    menuHeading(screenText(p['name'])),
                    Text(
                      'Quantité : ${screenText(p['quantity'])}',
                      style: const TextStyle(color: menuMuted),
                    ),
                    Text(
                      'Prix unitaire : ${screenMoney(p['price'])}',
                      style: const TextStyle(color: menuMuted),
                    ),
                    Text(
                      'Montant : ${screenMoney(p['amount'])}',
                      style: const TextStyle(
                        color: menuBlue,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        DataCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  menuIcon(Icons.person),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        menuHeading(screenText(client['name'])),
                        Text(
                          screenText(client['phone']),
                          style: const TextStyle(color: menuMuted),
                        ),
                        Text(
                          screenText(client['city']),
                          style: const TextStyle(color: menuMuted),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if (screenText(client['phone'], '').isNotEmpty)
                Align(
                  alignment: Alignment.centerRight,
                  child: menuButton(
                    'Contacter le client',
                    () async {
                      try {
                        await launchUrl(
                          Uri(scheme: 'tel', path: '${client['phone']}'),
                        );
                      } catch (e) {
                        if (context.mounted) menuError(context, e);
                      }
                    },
                    outlined: true,
                    icon: Icons.phone_outlined,
                  ),
                ),
            ],
          ),
        ),
        DataCard(
          title: widget.disputes ? 'Motif du litige' : 'Motif du retour',
          child: Text(
            screenText(data['reason']),
            style: const TextStyle(color: menuBlue),
          ),
        ),
        DataCard(
          title: 'Pièces jointes (${photos.length})',
          icon: Icons.attach_file,
          child: photos.isEmpty
              ? menuEmpty('Aucune pièce jointe')
              : Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: photos
                      .map(
                        (p) => InkWell(
                          onTap: () => showDialog(
                            context: context,
                            builder: (ctx) => Dialog(
                              child: Stack(
                                children: [
                                  InteractiveViewer(
                                    child: Image.network(
                                      '$p',
                                      errorBuilder: (_, e, s) => const Padding(
                                        padding: EdgeInsets.all(30),
                                        child: Text('Image indisponible'),
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    right: 0,
                                    top: 0,
                                    child: IconButton(
                                      onPressed: () => Navigator.pop(ctx),
                                      icon: const Icon(Icons.close),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          child: menuImage(p, size: 100),
                        ),
                      )
                      .toList(),
                ),
        ),
        DataCard(
          title: 'Historique du dossier',
          icon: Icons.history,
          child: rowsOf(data['timeline']).isEmpty
              ? menuEmpty('Aucun événement enregistré')
              : Column(
                  children: rowsOf(data['timeline'])
                      .map(
                        (t) => Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.check_circle_outline,
                                color: menuBlue,
                                size: 20,
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  screenText(t['label']),
                                  style: const TextStyle(color: menuBlue),
                                ),
                              ),
                              Text(
                                dateLabel(t['date']),
                                style: const TextStyle(
                                  color: menuMuted,
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ),
                        ),
                      )
                      .toList(),
                ),
        ),
        if (widget.disputes) ...[
          DataCard(
            title: 'Solution proposée',
            child: TextField(
              controller: response,
              maxLines: 4,
              maxLength: 2000,
              decoration: const InputDecoration(
                hintText: 'Décrivez votre réponse au client',
                border: OutlineInputBorder(),
              ),
            ),
          ),
          dataPair(
            menuButton(
              'Transmettre à OVANIE',
              busy ? null : () => action('escalate'),
              outlined: true,
              icon: Icons.support_agent,
            ),
            menuButton(
              'Traiter le litige',
              busy ? null : () => action('respond'),
              icon: Icons.send_outlined,
            ),
          ),
        ] else if (data['can_decide'] == true)
          dataPair(
            menuButton(
              'Refuser le retour',
              busy ? null : () => action('reject'),
              outlined: true,
              icon: Icons.cancel_outlined,
            ),
            menuButton(
              'Valider le retour',
              busy ? null : () => action('accept'),
              icon: Icons.check,
            ),
          )
        else
          menuInfo(
            'Le remboursement et la suite logistique suivent le traitement du dossier par OVANIE.',
          ),
      ],
    );
  }
}
