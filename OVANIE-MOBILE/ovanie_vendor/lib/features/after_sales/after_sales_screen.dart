import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
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
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      );
      final path = result?.files.single.path;
      if (path != null && mounted) setState(() => rejectionProof = File(path));
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  void _warn(String message) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));

  Future<bool> _confirm(String title, String message, String confirmLabel, {Color color = Mk.orange}) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => MkTheme(
        lightStatusBar: false,
        child: Dialog(
          backgroundColor: Colors.white,
          insetPadding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 22, 18, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: menuBlue, fontSize: 19, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: menuMuted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: () => Navigator.pop(dialog, false),
                        child: const Text('Annuler', style: TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w600)),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MkBarButton(label: confirmLabel, filled: true, color: color, onTap: () => Navigator.pop(dialog, true)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
    return ok == true;
  }

  Future<void> action(String action) async {
    final text = response.text.trim();
    if (action == 'respond' && text.isEmpty) return _warn('Rédigez une solution avant de l’envoyer.');
    if (action == 'reject' && text.length < 10) return _warn('Expliquez le refus en au moins 10 caractères.');
    if (action == 'reject' && rejectionProof == null) return _warn('Ajoutez un justificatif avant de refuser ce retour.');

    final confirmed = switch (action) {
      'accept' => await _confirm('Valider ce retour ?', 'Le client sera informé que sa demande de retour est acceptée.', 'Valider', color: menuGreen),
      'refund' => await _confirm('Rembourser le client ?', 'Le remboursement sera traité par OVANIE. Cette décision ne peut pas être annulée.', 'Rembourser'),
      'reject' => await _confirm('Refuser ce retour ?', 'Votre explication et votre justificatif seront transmis au client et à OVANIE.', 'Refuser', color: menuRed),
      'escalate' => await _confirm('Transmettre à OVANIE ?', 'L’équipe OVANIE prendra le relais pour traiter ce litige avec le client.', 'Transmettre', color: orderBlue),
      _ => true,
    };
    if (!confirmed || !mounted) return;

    setState(() => busy = true);
    try {
      await menuApi(
        '$path/${widget.id}/$action',
        method: 'POST',
        data: {
          if (action == 'respond') 'response': text,
          if (!widget.disputes) 'vendor_response': text,
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
                    if (p['price'] != null) _bullet('Prix unitaire', orderMoney(p['price'])),
                    if (p['amount'] != null) _bullet('Montant', orderMoney(p['amount'])),
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
