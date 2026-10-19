import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/mk_dialogs.dart';
import '../../core/ui/mk_file_pick.dart';
import '../menu/menu_ui.dart';
import '../orders/order_detail_screen.dart';
import '../orders/order_widgets.dart' show showOrderPhoneSheet;

/// Retours et litiges : la même liste et le même détail, selon [disputes].
class AfterSalesScreen extends StatefulWidget {
  const AfterSalesScreen({super.key, this.disputes = false});
  final bool disputes;
  @override
  State<AfterSalesScreen> createState() => _AfterSalesState();
}

const _newStatuses = ['open', 'new'];
const _runningStatuses = ['accepted', 'in_progress', 'processing', 'waiting_ovanie'];
const _doneStatuses = ['resolved', 'closed', 'refunded', 'rejected'];

class _AfterSalesState extends State<AfterSalesScreen> {
  List<Map<String, dynamic>> rows = [];
  bool loading = true, loaded = false;
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
      loaded = true;
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> open(Map<String, dynamic> r) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => CaseDetailScreen(id: number(r['id']).toInt(), disputes: widget.disputes)),
    );
    if (mounted) await load();
  }

  bool _matches(Map<String, dynamic> r, List<String> statuses) {
    if (statuses.isEmpty) return true;
    return statuses.contains('${r['status']}');
  }

  @override
  Widget build(BuildContext context) {
    // Chaque onglet regroupe les statuts équivalents envoyés par le serveur.
    final groups = widget.disputes
        ? <List<String>>[[], _newStatuses, ['in_progress', 'processing'], ['waiting_ovanie'], ['resolved', 'closed']]
        : <List<String>>[[], ['pending'], ['accepted'], ['rejected'], ['refunded']];
    final labels = widget.disputes
        ? ['Tous', 'Nouveaux', 'En cours', 'Attente OVANIE', 'Résolus']
        : ['Tous', 'En attente', 'Validés', 'Refusés', 'Remboursés'];
    final needle = query.trim().toLowerCase();
    final selected = rows.where((r) {
      final text = [r['product_name'], r['reference'], r['order_number'], r['client_name'], r['reason'], r['subject']]
          .join(' ')
          .toLowerCase();
      return text.contains(needle) && _matches(r, groups[filter]);
    }).toList();
    int count(List<String> statuses) => rows.where((r) => statuses.contains('${r['status']}')).length;

    return MenuPage(
      title: widget.disputes ? 'Litiges' : 'Retours',
      subtitle: widget.disputes
          ? 'Suivez et traitez les réclamations de vos clients'
          : 'Gérez les demandes de retour de vos clients',
      showLogo: true,
      refresh: load,
      loading: loading,
      error: error,
      hasContent: loaded,
      children: [
        MenuMetrics(
          items: [
            ('Tous', '${rows.length}', Icons.inventory_2_outlined, menuAzure),
            (
              widget.disputes ? 'Nouveaux' : 'En attente',
              '${count(widget.disputes ? _newStatuses : ['pending'])}',
              Icons.error_outline_rounded,
              menuRed,
            ),
            ('En cours', '${count(_runningStatuses)}', Icons.schedule_rounded, menuOrange),
            ('Traités', '${count(_doneStatuses)}', Icons.check_circle_rounded, menuGreen),
          ],
        ),
        menuSearch(
          (v) => setState(() => query = v),
          widget.disputes ? 'Rechercher un litige…' : 'Rechercher un retour…',
        ),
        menuFilters(labels, filter, (v) => setState(() => filter = v)),
        menuCaption(widget.disputes ? 'Liste des litiges' : 'Liste des retours'),
        if (selected.isEmpty)
          menuEmpty(
            rows.isEmpty
                ? (widget.disputes ? 'Aucun litige' : 'Aucun retour')
                : 'Aucun dossier ne correspond',
            icon: widget.disputes ? Icons.gavel_rounded : Icons.assignment_return_outlined,
            hint: rows.isEmpty
                ? (widget.disputes
                      ? 'Les réclamations de vos clients apparaîtront ici.'
                      : 'Les demandes de retour de vos clients apparaîtront ici.')
                : 'Modifiez la recherche ou le filtre.',
          ),
        for (final r in selected) _card(r),
      ],
    );
  }

  Widget _card(Map<String, dynamic> r) {
    final reason = screenText(r['reason'] ?? r['subject'], '');
    final date = menuDate(r['created_at']);
    const line = TextStyle(color: menuMuted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => open(r),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 6, 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                menuImage(r['image_url'], size: 72),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        screenText(r['product_name'], screenText(r['reference'], 'Dossier')),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: menuBlue, fontSize: 14.5, height: 1.25, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 2),
                      if (screenText(r['reference'], '').isNotEmpty) Text('${r['reference']}', style: line),
                      if (screenText(r['order_number'], '').isNotEmpty) Text('Commande ${r['order_number']}', style: line),
                      if (screenText(r['client_name'], '').isNotEmpty) Text('Client : ${r['client_name']}', style: line),
                      if (reason.isNotEmpty)
                        Text(
                          reason,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: line.copyWith(color: Mk.icon, fontStyle: FontStyle.italic),
                        ),
                      const SizedBox(height: 7),
                      Wrap(
                        spacing: 10,
                        runSpacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          menuPill(caseStatus(r['status']), color: caseColor(r['status'])),
                          if (date.isNotEmpty) Text(date, style: line),
                          if (r['amount'] != null)
                            Text(
                              orderMoney(r['amount']),
                              style: const TextStyle(color: menuBlue, fontSize: 14, fontWeight: FontWeight.w800),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.only(top: 22),
                  child: Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
                ),
              ],
            ),
          ),
        ),
      ),
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
  Map<String, dynamic> data = {};
  bool loading = true, busy = false;
  String? error;
  File? rejectionProof;
  final response = TextEditingController();
  String get path => widget.disputes ? 'disputes' : 'returns';

  int get _limit => widget.disputes ? 2000 : 1000;

  @override
  void initState() {
    super.initState();
    response.addListener(() => setState(() {}));
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
      data = mapOf((await menuApi('$path/${widget.id}'))['case']);
      response.text = screenText(data['response'], '');
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> pickRejectionProof() async {
    try {
      final file = await pickMkFile(
        context,
        title: 'Preuve du refus',
        message: 'Photo ou PDF qui justifie votre décision.',
        extensions: const ['pdf'],
      );
      if (file != null && mounted) setState(() => rejectionProof = file);
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  void _warn(String message) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));

  /// Rappel du dossier dans les confirmations : produit, référence, client, montant.
  Widget _caseSummary({bool withStatus = false}) {
    final p = mapOf(data['product']), client = mapOf(data['client']);
    final amount = p['amount'] ?? data['amount'];
    return MkSheetSummary(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          menuImage(p['image_url'] ?? data['image_url'], size: 76, radius: 10),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        screenText(p['name'] ?? data['product_name'], 'Produit'),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: menuBlue, fontSize: 15, height: 1.25, fontWeight: FontWeight.w800),
                      ),
                    ),
                    if (withStatus) ...[
                      const SizedBox(width: 6),
                      menuPill(caseStatus(data['status']), color: caseColor(data['status']), icon: Icons.error_outline_rounded),
                    ],
                  ],
                ),
                if (screenText(data['reference'], '').isNotEmpty)
                  Text('${data['reference']}', style: const TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w500)),
                if (screenText(client['name'], '').isNotEmpty)
                  Text(
                    'Client : ${client['name']}',
                    style: const TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w500),
                  ),
                if (amount != null)
                  Text(orderMoney(amount), style: const TextStyle(color: menuBlue, fontSize: 16, fontWeight: FontWeight.w800)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static const _ovanieNote = MkInfoBox(
    icon: Icons.info_rounded,
    child: Text('Le remboursement et la suite logistique suivent le traitement du dossier par OVANIE.'),
  );

  Future<bool> _confirmAction(String action) async {
    switch (action) {
      case 'accept':
        return showMkConfirmSheet(
          context,
          icon: Icons.check_rounded,
          iconColor: menuGreen,
          title: 'Valider le retour ?',
          summary: _caseSummary(),
          body: _ovanieNote,
          confirmLabel: 'Valider le retour',
          confirmIcon: Icons.check_circle_rounded,
          confirmColor: menuGreen,
          cancelColor: orderBlue,
        );
      case 'refund':
        final amount = mapOf(data['product'])['amount'] ?? data['amount'];
        return showMkConfirmSheet(
          context,
          icon: Icons.currency_exchange_rounded,
          iconColor: Mk.orange,
          title: 'Rembourser le client ?',
          summary: _caseSummary(),
          body: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (amount != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: Mk.softOrange,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFFD2B3)),
                  ),
                  child: Row(
                    children: [
                      const Text('Montant', style: TextStyle(color: Color(0xFFE85400), fontSize: 14.5, fontWeight: FontWeight.w700)),
                      const SizedBox(width: 14),
                      Expanded(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.centerLeft,
                          child: Text(
                            orderMoney(amount),
                            style: const TextStyle(color: Color(0xFFE85400), fontSize: 26, fontWeight: FontWeight.w800),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 10),
              _ovanieNote,
            ],
          ),
          confirmLabel: 'Rembourser le client',
          confirmIcon: Icons.currency_exchange_rounded,
          cancelColor: orderBlue,
        );
      case 'reject':
        final ok = await showMkSheet<bool>(
          context,
          builder: (_) => _RejectSheet(
            summary: _caseSummary(),
            response: response,
            proof: rejectionProof,
            onProof: (file) => setState(() => rejectionProof = file),
          ),
        );
        return ok == true;
      case 'escalate':
        return showMkConfirmSheet(
          context,
          icon: Icons.support_agent_rounded,
          iconColor: orderBlue,
          title: 'Transmettre à OVANIE ?',
          summary: _caseSummary(withStatus: true),
          message: 'Le dossier passera au statut En attente OVANIE.',
          confirmLabel: 'Transmettre à OVANIE',
          confirmIcon: Icons.support_agent_rounded,
          confirmColor: orderBlue,
          cancelColor: orderBlue,
        );
      default:
        return true;
    }
  }

  Future<void> action(String action) async {
    final text = response.text.trim();
    if (action == 'respond' && text.isEmpty) return _warn('Rédigez une solution avant de l’envoyer.');

    final confirmed = await _confirmAction(action);
    if (!confirmed || !mounted) return;
    final finalText = response.text.trim();

    setState(() => busy = true);
    try {
      await menuApi(
        '$path/${widget.id}/$action',
        method: 'POST',
        data: {
          if (action == 'respond') 'response': finalText,
          if (!widget.disputes) 'vendor_response': finalText,
        },
        files: action == 'reject' && rejectionProof != null ? {'vendor_rejection_proof': rejectionProof!} : null,
      );
      if (mounted) {
        _warn(const {
              'accept': 'Retour validé.',
              'refund': 'Remboursement demandé.',
              'reject': 'Retour refusé.',
              'respond': 'Votre réponse a été envoyée au client.',
              'escalate': 'Litige transmis à OVANIE.',
            }[action] ??
            'Dossier mis à jour.');
      }
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
    final photos = ((data['attachments'] as List?) ?? []).map((e) => '$e').where((e) => e.isNotEmpty).toList();
    final timeline = rowsOf(data['timeline']);
    final status = data['status'];
    final details = screenText(data['description'] ?? data['details'], '');
    final reason = screenText(data['reason'] ?? data['subject'], '');
    final phone = screenText(client['phone'], '');
    final closed = _doneStatuses.contains('$status');

    return MenuPage(
      title: widget.disputes ? 'Détail du litige' : 'Détail du retour',
      subtitle: 'Consultez les informations et le suivi de ce dossier',
      refresh: load,
      loading: loading || busy,
      error: error,
      hasContent: data.isNotEmpty,
      children: [
        // ---- Dossier ---------------------------------------------------------
        _card(
          caption: 'Dossier',
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  children: [
                    _kv(widget.disputes ? 'ID du litige' : 'ID du retour', screenText(data['reference']), strong: true),
                    _kv(
                      'Commande',
                      screenText(data['order_number']),
                      onTap: data['order_id'] == null
                          ? null
                          : () => openMenuPage(context, OrderDetailScreen(orderId: number(data['order_id']).toInt())),
                    ),
                    _kv('Date de demande', screenText(menuDate(data['created_at']))),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 150),
                child: menuPill(
                  caseStatus(status),
                  color: caseColor(status),
                  icon: closed ? Icons.check_circle_outline_rounded : Icons.error_outline_rounded,
                ),
              ),
            ],
          ),
        ),

        // ---- Produit ---------------------------------------------------------
        _card(
          caption: 'Produit',
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              menuImage(p['image_url'], size: 84),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    menuHeading(screenText(p['name'], 'Produit')),
                    const SizedBox(height: 4),
                    if (p['quantity'] != null) _bullet('Quantité', screenText(p['quantity'])),
                    if (p['price'] != null) _bullet('Prix payé par le client', orderMoney(p['price'])),
                    if (p['amount'] != null) _bullet('Montant concerné', orderMoney(p['amount'])),
                  ],
                ),
              ),
            ],
          ),
        ),

        // ---- Client ----------------------------------------------------------
        _card(
          caption: 'Client',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  menuIcon(Icons.person_rounded, color: Mk.icon, size: 50),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        menuHeading(screenText(client['name'], 'Client OVANIE')),
                        if (phone.isNotEmpty)
                          Text(menuPhone(phone), style: const TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500)),
                        if (screenText(client['city'], '').isNotEmpty)
                          Row(
                            children: [
                              const Icon(Icons.location_on_rounded, color: Mk.icon, size: 16),
                              const SizedBox(width: 4),
                              Text('${client['city']}', style: const TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500)),
                            ],
                          ),
                      ],
                    ),
                  ),
                ],
              ),
              if (phone.isEmpty && data['handled_by_ovanie'] == true) ...[
                const SizedBox(height: 10),
                const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_rounded, color: Mk.hint, size: 20),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Commande livrée par OVANIE Logistics : OVANIE gère le contact avec le client.',
                        style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                      ),
                    ),
                  ],
                ),
              ],
              if (phone.isNotEmpty) ...[
                const SizedBox(height: 10),
                MkBarButton(
                  label: 'Contacter le client',
                  icon: Icons.call_rounded,
                  color: orderBlue,
                  height: 44,
                  onTap: () => showOrderPhoneSheet(context, name: screenText(client['name'], 'Client'), phone: phone),
                ),
              ],
            ],
          ),
        ),

        // ---- Motif -----------------------------------------------------------
        _card(
          title: widget.disputes ? 'Motif du litige' : 'Motif du retour',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                reason.isEmpty ? 'Motif non précisé par le client' : reason,
                style: const TextStyle(color: menuBlue, fontSize: 14.5, height: 1.35, fontWeight: FontWeight.w700),
              ),
              if (details.isNotEmpty && details != reason)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(details, style: const TextStyle(color: menuMuted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500)),
                ),
            ],
          ),
        ),

        // ---- Pièces jointes ----------------------------------------------------
        _card(
          title: 'Pièces jointes (${photos.length})',
          child: photos.isEmpty
              ? const Text(
                  'Le client n’a joint aucune photo à ce dossier.',
                  style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
                )
              : LayoutBuilder(
                  builder: (context, box) {
                    final width = (box.maxWidth - 8) / 2;
                    return Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final photo in photos)
                          Semantics(
                            button: true,
                            label: 'Agrandir la pièce jointe',
                            child: GestureDetector(
                              onTap: () => _showPhoto(photo),
                              child: menuImage(photo, size: width * .62, width: width, radius: 12),
                            ),
                          ),
                      ],
                    );
                  },
                ),
        ),

        // ---- Historique --------------------------------------------------------
        _card(
          title: 'Historique du dossier',
          icon: Icons.history_rounded,
          child: timeline.isEmpty
              ? const Text(
                  'Aucun événement enregistré pour le moment.',
                  style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
                )
              : Column(
                  children: [
                    for (var i = 0; i < timeline.length; i++)
                      IntrinsicHeight(
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Column(
                              children: [
                                Icon(
                                  i == 0 ? Icons.radio_button_checked_rounded : Icons.check_circle_rounded,
                                  color: i == 0 ? Mk.orange : menuGreen,
                                  size: 22,
                                ),
                                if (i < timeline.length - 1) Expanded(child: Container(width: 2, color: orderBorder)),
                              ],
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Padding(
                                padding: EdgeInsets.only(bottom: i < timeline.length - 1 ? 12 : 0),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      screenText(timeline[i]['label'], 'Mise à jour'),
                                      style: const TextStyle(color: menuBlue, fontSize: 13.5, fontWeight: FontWeight.w700),
                                    ),
                                    if (menuDate(timeline[i]['date']).isNotEmpty)
                                      Text(
                                        menuDate(timeline[i]['date']),
                                        style: const TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
        ),

        // ---- Réponse et décisions ------------------------------------------------
        if (widget.disputes) ...[
          _card(title: 'Solution proposée', child: _responseField('Décrivez votre réponse au client')),
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: MkBarButton(
              label: 'Traiter le litige',
              icon: Icons.send_rounded,
              filled: true,
              onTap: busy ? null : () => action('respond'),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: MkBarButton(
              label: 'Transmettre à OVANIE',
              icon: Icons.support_agent_rounded,
              color: orderBlue,
              onTap: busy ? null : () => action('escalate'),
            ),
          ),
          _footnote('OVANIE peut vous accompagner dans le traitement de ce litige si nécessaire.'),
        ] else if (data['can_decide'] == true) ...[
          _card(
            title: 'Réponse au client',
            child: _responseField('Expliquez votre décision au client (obligatoire pour refuser, au moins 10 caractères)'),
          ),
          _card(
            title: 'Justificatif de refus',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Une photo ou un document est obligatoire pour refuser un retour.',
                  style: TextStyle(color: menuMuted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
                ),
                if (rejectionProof != null) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.insert_drive_file_outlined, color: menuGreen, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          rejectionProof!.path.split(RegExp(r'[\\/]')).last,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: menuBlue, fontSize: 13.5, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: busy ? null : pickRejectionProof,
                  icon: const Icon(Icons.attach_file_rounded, size: 19),
                  label: Text(rejectionProof == null ? 'Ajouter un justificatif' : 'Remplacer le justificatif'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: orderBlue,
                    side: const BorderSide(color: orderBlue),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    textStyle: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: MkBarButton(
              label: 'Valider le retour',
              icon: Icons.check_circle_rounded,
              filled: true,
              color: menuGreen,
              onTap: busy ? null : () => action('accept'),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: MkBarButton(
              label: 'Rembourser le client',
              icon: Icons.sync_rounded,
              filled: true,
              onTap: busy ? null : () => action('refund'),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: MkBarButton(
              label: 'Refuser le retour',
              icon: Icons.close_rounded,
              color: menuRed,
              onTap: busy ? null : () => action('reject'),
            ),
          ),
          _footnote('Le remboursement et la suite logistique suivent le traitement du dossier par OVANIE.'),
        ] else
          menuInfo('Le remboursement et la suite logistique suivent le traitement du dossier par OVANIE.'),
      ],
    );
  }

  void _showPhoto(String url) => showDialog<void>(
    context: context,
    builder: (ctx) => Dialog(
      backgroundColor: Colors.black,
      insetPadding: const EdgeInsets.all(12),
      child: Stack(
        children: [
          InteractiveViewer(
            child: Image.network(
              url,
              errorBuilder: (_, e, s) => const Padding(
                padding: EdgeInsets.all(40),
                child: Text('Image indisponible', style: TextStyle(color: Colors.white)),
              ),
            ),
          ),
          Positioned(
            right: 4,
            top: 4,
            child: IconButton(
              tooltip: 'Fermer',
              onPressed: () => Navigator.pop(ctx),
              icon: const Icon(Icons.close_rounded, color: Colors.white),
            ),
          ),
        ],
      ),
    ),
  );

  Widget _card({String? caption, String? title, IconData? icon, required Widget child}) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (caption != null) ...[OrderCaption(caption), const SizedBox(height: 8)],
        if (title != null) ...[
          Row(
            children: [
              if (icon != null) ...[Icon(icon, color: Mk.icon, size: 21), const SizedBox(width: 8)],
              Expanded(child: menuHeading(title)),
            ],
          ),
          const SizedBox(height: 8),
        ],
        child,
      ],
    ),
  );

  Widget _kv(String label, String value, {bool strong = false, VoidCallback? onTap}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 112,
          child: Text(label, style: const TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w500)),
        ),
        Expanded(
          child: GestureDetector(
            onTap: onTap,
            child: Text(
              value,
              style: TextStyle(
                color: onTap != null ? orderBlue : menuBlue,
                fontSize: 13.5,
                fontWeight: strong || onTap != null ? FontWeight.w800 : FontWeight.w500,
              ),
            ),
          ),
        ),
      ],
    ),
  );

  Widget _bullet(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 2),
    child: Text.rich(
      TextSpan(
        text: '•  $label : ',
        children: [
          TextSpan(text: value, style: const TextStyle(color: menuBlue, fontWeight: FontWeight.w800)),
        ],
      ),
      style: const TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500),
    ),
  );

  Widget _responseField(String hint) => Column(
    crossAxisAlignment: CrossAxisAlignment.end,
    children: [
      TextField(
        controller: response,
        enabled: !busy,
        minLines: 3,
        maxLines: 7,
        inputFormatters: [LengthLimitingTextInputFormatter(_limit)],
        style: mkFieldText.copyWith(height: 1.45),
        decoration: mkInput(hint: hint),
      ),
      const SizedBox(height: 4),
      Text(
        '${response.text.characters.length}/$_limit',
        style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
      ),
    ],
  );

  Widget _footnote(String text) => Padding(
    padding: const EdgeInsets.fromLTRB(4, 0, 4, 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.info_outline_rounded, color: Mk.hint, size: 18),
        const SizedBox(width: 8),
        Expanded(
          child: Text(text, style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500)),
        ),
      ],
    ),
  );
}

/// Feuille « Refuser le retour ? » : explication au client et justificatif
/// obligatoires, vérifiés avant l'envoi.
class _RejectSheet extends StatefulWidget {
  const _RejectSheet({required this.summary, required this.response, required this.proof, required this.onProof});
  final Widget summary;
  final TextEditingController response;
  final File? proof;
  final ValueChanged<File> onProof;
  @override
  State<_RejectSheet> createState() => _RejectSheetState();
}

class _RejectSheetState extends State<_RejectSheet> {
  late File? _proof = widget.proof;
  String? _error;

  bool get _isImage => RegExp(r'\.(jpe?g|png|webp)$', caseSensitive: false).hasMatch(_proof?.path ?? '');

  Future<void> _pick() async {
    try {
      final file = await pickMkFile(
        context,
        title: 'Preuve du refus',
        message: 'Photo ou PDF qui justifie votre décision.',
        extensions: const ['pdf'],
      );
      if (file == null || !mounted) return;
      widget.onProof(file);
      setState(() {
        _proof = file;
        _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    }
  }

  void _confirm() {
    final text = widget.response.text.trim();
    final problem = text.length < 10
        ? 'Expliquez le refus au client en au moins 10 caractères.'
        : _proof == null
        ? 'Ajoutez une photo ou un document justificatif.'
        : null;
    if (problem != null) {
      setState(() => _error = problem);
      return;
    }
    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) => MkSheetFrame(
    children: [
      const Center(child: MkIconDisc(Icons.close_rounded, color: menuRed, size: 66)),
      const SizedBox(height: 10),
      const Text(
        'Refuser le retour ?',
        textAlign: TextAlign.center,
        style: TextStyle(color: menuBlue, fontSize: 21, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 12),
      widget.summary,
      const SizedBox(height: 10),
      MkSheetSummary(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Réponse au client', style: TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            TextField(
              controller: widget.response,
              minLines: 2,
              maxLines: 5,
              textCapitalization: TextCapitalization.sentences,
              inputFormatters: [LengthLimitingTextInputFormatter(1000)],
              style: mkFieldText.copyWith(height: 1.45),
              decoration: mkInput(hint: 'Expliquez pourquoi le retour est refusé'),
              onChanged: (_) {
                if (_error != null) setState(() => _error = null);
              },
            ),
            const SizedBox(height: 12),
            const Text('Justificatif de refus', style: TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Semantics(
                  button: true,
                  label: _proof == null ? 'Ajouter un justificatif' : 'Remplacer le justificatif',
                  child: GestureDetector(
                    onTap: _pick,
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: Container(
                            width: 110,
                            height: 82,
                            color: Mk.softBlue,
                            child: _proof == null
                                ? const Icon(Icons.add_photo_alternate_outlined, color: Mk.icon, size: 32)
                                : _isImage
                                ? Image.file(_proof!, fit: BoxFit.cover)
                                : const Icon(Icons.picture_as_pdf_outlined, color: menuRed, size: 34),
                          ),
                        ),
                        if (_proof != null)
                          const Positioned(
                            right: -6,
                            top: -6,
                            child: CircleAvatar(
                              radius: 13,
                              backgroundColor: Colors.white,
                              child: Icon(Icons.check_circle_rounded, color: menuGreen, size: 24),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    _proof == null
                        ? 'Une photo ou un document est obligatoire pour refuser un retour. Touchez le cadre pour l’ajouter.'
                        : _proof!.path.split(RegExp(r'[\\/]')).last,
                    style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      if (_error != null) ...[const SizedBox(height: 10), MkNotice(_error!)],
      const SizedBox(height: 16),
      Row(
        children: [
          Expanded(flex: 4, child: MkBarButton(label: 'Annuler', color: orderBlue, onTap: () => Navigator.pop(context, false))),
          const SizedBox(width: 10),
          Expanded(flex: 6, child: MkBarButton(label: 'Refuser le retour', filled: true, color: menuRed, onTap: _confirm)),
        ],
      ),
    ],
  );
}
