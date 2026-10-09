import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../menu/menu_ui.dart';
import 'vendor_ai_chat_screen.dart';

/// Centre d'assistance du vendeur : nouvelle demande, dossiers et échanges avec le support.
class VendorSupportScreen extends StatefulWidget {
  const VendorSupportScreen({
    super.key,
    this.initialCategory,
    this.initialContextType,
    this.initialContextId,
    this.initialSubject,
    this.startNew = false,
  });

  final String? initialCategory;
  final String? initialContextType;
  final int? initialContextId;
  final String? initialSubject;
  final bool startNew;

  @override
  State<VendorSupportScreen> createState() => _VendorSupportScreenState();
}

const _categories = {
  'general': 'Général',
  'shop': 'Boutique',
  'products': 'Produit',
  'orders': 'Commande',
  'payouts': 'Versement vendeur',
  'delivery': 'Livraison',
  'technical': 'Problème technique',
};

String _contextLabel(String type) =>
    const {
      'vendor_payout': 'versement',
      'order': 'commande',
      'shop': 'boutique',
      'product': 'produit',
      'shipment': 'livraison',
      'return': 'retour',
    }[type] ??
    'dossier';

String _statusLabel(dynamic raw) =>
    const {
      'open': 'Nouveau',
      'in_progress': 'Pris en charge',
      'waiting': 'En attente',
      'waiting_customer': 'Votre réponse attendue',
      'waiting_internal': 'Vérification en cours',
      'resolved': 'Terminé',
      'closed': 'Archivé',
      'cancelled': 'Annulé',
    }['$raw'] ??
    'En cours';

Color _statusColor(dynamic raw) => ['resolved', 'closed'].contains('$raw')
    ? menuGreen
    : '$raw' == 'waiting_customer'
    ? const Color(0xFFE85400)
    : '$raw' == 'cancelled'
    ? Mk.icon
    : menuAzure;

class _VendorSupportScreenState extends State<VendorSupportScreen> {
  Map<String, dynamic> _data = const {};
  String _shopName = '';
  bool _loading = true;
  bool _loaded = false;
  String? _error;
  bool _openedInitialComposer = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final response = await menuApi('support');
      if (!mounted) return;
      setState(() {
        _data = mapOf(response['data']);
        _loaded = true;
      });
      if (_shopName.isEmpty) _loadShopName();
      if (widget.startNew && !_openedInitialComposer) {
        _openedInitialComposer = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) _compose();
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Nom de la boutique pour la bannière ; l'écran reste complet sans lui.
  Future<void> _loadShopName() async {
    try {
      final shop = mapOf((await menuApi('shop'))['shop']);
      if (mounted) setState(() => _shopName = screenText(shop['name'], ''));
    } catch (_) {
      // La bannière affiche alors le nom du titulaire.
    }
  }

  List<Map<String, dynamic>> get _tickets => rowsOf(_data['tickets']);

  Future<void> _compose() async {
    final sent = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (_) => _ComposeSheet(
        initialCategory: widget.initialCategory,
        initialSubject: widget.initialSubject,
        contextType: widget.initialContextType,
        contextId: widget.initialContextId,
      ),
    );
    if (sent == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Votre demande a été envoyée au Support OVANIE.')),
      );
      await _load();
    }
  }

  Future<void> _openTicket(int id) async {
    try {
      final response = await menuApi('support/tickets/$id');
      final detail = mapOf(response['data']);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
        builder: (_) => _VendorTicketSheet(id: id, ticket: detail),
      );
      if (mounted) await _load();
    } catch (error) {
      if (mounted) menuError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tickets = _tickets;
    final following = tickets.where((t) => !['resolved', 'closed', 'cancelled'].contains('${t['status']}')).length;
    return MenuPage(
      title: 'Centre d’assistance',
      subtitle: 'Vos demandes vendeur, au même endroit',
      showLogo: true,
      refresh: _load,
      loading: _loading,
      error: _error,
      hasContent: _loaded,
      children: [
        _hero(),
        MenuMetrics(
          items: [
            ('À suivre', '$following', Icons.description_outlined, menuOrange),
            ('Total', '${tickets.length}', Icons.folder_open_rounded, menuAzure),
          ],
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const VendorAiChatScreen())),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 12, 8, 12),
                child: Row(
                  children: [
                    menuIcon(Icons.chat_bubble_outline_rounded, color: menuAzure, size: 42),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Discuter avec le support',
                            style: TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w700),
                          ),
                          Text(
                            'Réponse immédiate, relais vers un conseiller',
                            style: TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
                  ],
                ),
              ),
            ),
          ),
        ),
        Row(
          children: [
            const Expanded(
              child: Padding(
                padding: EdgeInsets.only(left: 4),
                child: Text('Mes dossiers', style: TextStyle(color: menuBlue, fontSize: 19, fontWeight: FontWeight.w800)),
              ),
            ),
            IconButton(
              tooltip: 'Actualiser',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.sync_rounded, color: Mk.icon),
            ),
          ],
        ),
        const SizedBox(height: 4),
        if (tickets.isEmpty)
          menuEmpty(
            'Aucune demande pour le moment',
            icon: Icons.mark_chat_read_outlined,
            hint: 'Vos demandes et les réponses de l’équipe OVANIE apparaîtront ici.',
          ),
        for (final ticket in tickets) _ticketCard(ticket),
        menuInfo(
          'Pour une commande, un versement ou une livraison, ouvrez l’assistance depuis l’écran concerné : '
          'OVANIE associera automatiquement le bon contexte à votre dossier.',
        ),
      ],
    );
  }

  Widget _hero() {
    final account = _shopName.isNotEmpty ? _shopName : screenText(mapOf(_data['requester'])['name'], '');
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: const LinearGradient(
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
          colors: [Color(0xFF031945), Color(0xFF0A3A9A)],
        ),
      ),
      child: Stack(
        children: [
          // Le conseiller occupe le tiers droit : le texte reste lisible à gauche.
          Positioned.fill(
            child: FractionallySizedBox(
              widthFactor: .4,
              heightFactor: 1,
              alignment: Alignment.centerRight,
              child: ShaderMask(
              blendMode: BlendMode.dstIn,
              shaderCallback: (rect) =>
                  const LinearGradient(colors: [Colors.transparent, Colors.white], stops: [0, .35]).createShader(rect),
              child: Image.asset(
                'assets/images/mockups/support_agent.jpg',
                fit: BoxFit.cover,
                alignment: Alignment.topCenter,
                excludeFromSemantics: true,
              ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: .14), shape: BoxShape.circle),
                  child: const Icon(Icons.headset_mic_rounded, color: Colors.white, size: 26),
                ),
                const SizedBox(height: 12),
                const FractionallySizedBox(
                  widthFactor: .6,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Support Vendeur OVANIE',
                    style: TextStyle(color: Colors.white, fontSize: 18, height: 1.2, fontWeight: FontWeight.w800),
                  ),
                ),
                if (account.isNotEmpty)
                  FractionallySizedBox(
                    widthFactor: .6,
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Compte : $account',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Color(0xFFBFD3F6), fontSize: 13, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ),
                const SizedBox(height: 14),
                FractionallySizedBox(
                  widthFactor: .62,
                  alignment: Alignment.centerLeft,
                  child: Material(
                    color: Mk.orange,
                    borderRadius: BorderRadius.circular(12),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: _compose,
                      child: const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 12, vertical: 13),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Flexible(
                              child: Text(
                                'Nouvelle demande',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700),
                              ),
                            ),
                            SizedBox(width: 8),
                            Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 20),
                          ],
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

  Widget _ticketCard(Map<String, dynamic> ticket) {
    final color = _statusColor(ticket['status']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => _openTicket(number(ticket['id']).toInt()),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 6, 12),
            child: Row(
              children: [
                menuIcon(Icons.headset_mic_rounded, color: color, size: 46),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (screenText(ticket['reference'], '').isNotEmpty)
                        Text(
                          '${ticket['reference']}',
                          style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w600),
                        ),
                      Text(
                        screenText(ticket['subject'], 'Demande d’assistance'),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: menuBlue, fontSize: 14.5, height: 1.25, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      menuPill(_statusLabel(ticket['status']), color: color),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

Widget _sheetHandle() => Center(
  child: Container(
    width: 44,
    height: 5,
    decoration: BoxDecoration(color: const Color(0xFFD5DBE7), borderRadius: BorderRadius.circular(99)),
  ),
);

/// Feuille « Nouvelle demande ».
class _ComposeSheet extends StatefulWidget {
  const _ComposeSheet({this.initialCategory, this.initialSubject, this.contextType, this.contextId});
  final String? initialCategory, initialSubject, contextType;
  final int? contextId;
  @override
  State<_ComposeSheet> createState() => _ComposeSheetState();
}

class _ComposeSheetState extends State<_ComposeSheet> {
  final _form = GlobalKey<FormState>();
  late String _category = _categories.containsKey(widget.initialCategory) ? widget.initialCategory! : 'general';
  late final _subject = TextEditingController(text: widget.initialSubject ?? '');
  final _description = TextEditingController();
  bool _sending = false;
  String? _error;

  bool get _hasContext => widget.contextType != null && widget.contextId != null;

  @override
  void dispose() {
    _subject.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await menuApi(
        'support/tickets',
        method: 'POST',
        data: {
          'category': _category,
          'subject': _subject.text.trim(),
          'description': _description.text.trim(),
          if (_hasContext)
            'contexts': [
              {'type': widget.contextType, 'id': widget.contextId},
            ],
        },
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MkTheme(
      lightStatusBar: false,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .9),
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 18),
              child: Form(
                key: _form,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _sheetHandle(),
                    const SizedBox(height: 14),
                    const Text('Nouvelle demande', style: TextStyle(color: menuBlue, fontSize: 21, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    const Text(
                      'Votre demande sera liée à votre compte vendeur et à votre boutique.',
                      style: TextStyle(color: menuMuted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
                    ),
                    if (_hasContext) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(11),
                        decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(11)),
                        child: Row(
                          children: [
                            const Icon(Icons.info_outline_rounded, color: orderBlue, size: 20),
                            const SizedBox(width: 9),
                            Expanded(
                              child: Text.rich(
                                TextSpan(
                                  text: 'Contexte automatiquement associé : ',
                                  children: [
                                    TextSpan(
                                      text: _contextLabel(widget.contextType!),
                                      style: const TextStyle(fontWeight: FontWeight.w800),
                                    ),
                                  ],
                                ),
                                style: const TextStyle(color: orderBlue, fontSize: 13, fontWeight: FontWeight.w500),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    const MkLabel('Catégorie', required: true),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final entry in _categories.entries)
                          SizedBox(
                            height: 38,
                            child: menuChip(
                              entry.value,
                              _category == entry.key,
                              () {
                                if (!_sending) setState(() => _category = entry.key);
                              },
                              activeColor: Mk.orange,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    const MkLabel('Objet', required: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _subject,
                      enabled: !_sending,
                      textCapitalization: TextCapitalization.sentences,
                      inputFormatters: [LengthLimitingTextInputFormatter(160)],
                      style: mkFieldText,
                      decoration: mkInput(hint: 'Résumez votre demande en une phrase'),
                      validator: (v) => (v ?? '').trim().length < 4 ? 'Indiquez un objet d’au moins 4 caractères.' : null,
                    ),
                    const SizedBox(height: 14),
                    const MkLabel('Décrivez précisément votre demande', required: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _description,
                      enabled: !_sending,
                      minLines: 5,
                      maxLines: 9,
                      textCapitalization: TextCapitalization.sentences,
                      inputFormatters: [LengthLimitingTextInputFormatter(4000)],
                      style: mkFieldText.copyWith(height: 1.45),
                      decoration: mkInput(hint: 'Expliquez la situation, les références concernées et ce que vous attendez.'),
                      validator: (v) => (v ?? '').trim().length < 10 ? 'Décrivez votre demande en au moins 10 caractères.' : null,
                    ),
                    if (_error != null) ...[const SizedBox(height: 12), MkNotice(_error!)],
                    const SizedBox(height: 16),
                    MkBarButton(
                      label: 'Envoyer au support',
                      icon: Icons.send_rounded,
                      filled: true,
                      loading: _sending,
                      onTap: _sending ? null : _send,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Feuille d'un dossier : demande d'origine, échanges et réponse.
class _VendorTicketSheet extends StatefulWidget {
  const _VendorTicketSheet({required this.id, required this.ticket});
  final int id;
  final Map<String, dynamic> ticket;
  @override
  State<_VendorTicketSheet> createState() => _VendorTicketSheetState();
}

class _VendorTicketSheetState extends State<_VendorTicketSheet> {
  final _reply = TextEditingController();
  late Map<String, dynamic> _ticket = widget.ticket;
  bool _sending = false;

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _reply.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    try {
      await menuApi('support/tickets/${widget.id}/messages', method: 'POST', data: {'message': text});
      final refreshed = mapOf((await menuApi('support/tickets/${widget.id}'))['data']);
      if (!mounted) return;
      setState(() {
        if (refreshed.isNotEmpty) _ticket = refreshed;
        _reply.clear();
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(e))));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  String _time(dynamic raw) {
    final date = DateTime.tryParse('${raw ?? ''}')?.toLocal();
    if (date == null) return '';
    final now = DateTime.now();
    final sameDay = date.year == now.year && date.month == now.month && date.day == now.day;
    final clock = '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    return sameDay ? clock : '${menuDate(raw)} · $clock';
  }

  @override
  Widget build(BuildContext context) {
    final messages = rowsOf(_ticket['messages']);
    final status = _ticket['status'];
    final open = !['closed', 'cancelled'].contains('$status');
    final description = screenText(_ticket['description'], '');
    return MkTheme(
      lightStatusBar: false,
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: DraggableScrollableSheet(
          expand: false,
          initialChildSize: .82,
          maxChildSize: .95,
          minChildSize: .55,
          builder: (context, scroll) => Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _sheetHandle(),
                const SizedBox(height: 12),
                if (screenText(_ticket['reference'], '').isNotEmpty)
                  Text('${_ticket['reference']}', style: const TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w600)),
                Text(
                  screenText(_ticket['subject'], 'Demande d’assistance'),
                  style: const TextStyle(color: menuBlue, fontSize: 19, height: 1.25, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Align(alignment: Alignment.centerLeft, child: menuPill(_statusLabel(status), color: _statusColor(status))),
                const SizedBox(height: 10),
                Expanded(
                  child: ListView(
                    controller: scroll,
                    children: [
                      if (description.isNotEmpty)
                        Container(
                          margin: const EdgeInsets.only(bottom: 14),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF5F7FB),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: orderBorder),
                          ),
                          child: Text(
                            description,
                            style: const TextStyle(color: Mk.icon, fontSize: 13.5, height: 1.45, fontWeight: FontWeight.w500),
                          ),
                        ),
                      if (messages.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 18),
                          child: Text(
                            'Aucune réponse pour le moment. L’équipe OVANIE vous répondra ici.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: menuMuted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
                          ),
                        ),
                      for (final m in messages) _bubble(m),
                    ],
                  ),
                ),
                if (open)
                  Container(
                    margin: const EdgeInsets.only(top: 8),
                    padding: const EdgeInsets.fromLTRB(14, 4, 5, 4),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(26),
                      border: Border.all(color: Mk.border, width: 1.2),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _reply,
                            enabled: !_sending,
                            minLines: 1,
                            maxLines: 4,
                            textCapitalization: TextCapitalization.sentences,
                            inputFormatters: [LengthLimitingTextInputFormatter(4000)],
                            style: mkFieldText,
                            decoration: const InputDecoration(
                              hintText: 'Votre réponse',
                              hintStyle: TextStyle(color: Mk.hint, fontSize: 14, fontWeight: FontWeight.w400),
                              border: InputBorder.none,
                              enabledBorder: InputBorder.none,
                              focusedBorder: InputBorder.none,
                              disabledBorder: InputBorder.none,
                              filled: false,
                              isDense: true,
                              contentPadding: EdgeInsets.symmetric(vertical: 12),
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Material(
                          color: Mk.orange,
                          shape: const CircleBorder(),
                          clipBehavior: Clip.antiAlias,
                          child: InkWell(
                            onTap: _sending ? null : _send,
                            child: SizedBox(
                              width: 42,
                              height: 42,
                              child: _sending
                                  ? const Padding(
                                      padding: EdgeInsets.all(12),
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                    )
                                  : const Icon(Icons.send_rounded, color: Colors.white, size: 20, semanticLabel: 'Envoyer'),
                            ),
                          ),
                        ),
                      ],
                    ),
                  )
                else
                  const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text(
                      'Ce dossier est clôturé. Ouvrez une nouvelle demande pour poursuivre.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _bubble(Map<String, dynamic> m) {
    final mine = ['vendor', 'client', 'driver', 'commercial'].contains('${m['author_type']}');
    final time = _time(m['created_at']);
    final bubble = Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
      constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .7),
      decoration: BoxDecoration(
        color: mine ? Mk.navy : const Color(0xFFEFF2F8),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(
        screenText(m['body'], ''),
        style: TextStyle(color: mine ? Colors.white : menuBlue, fontSize: 14, height: 1.4, fontWeight: FontWeight.w500),
      ),
    );
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: mine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (!mine) ...[menuIcon(Icons.headset_mic_rounded, color: menuOrange, size: 38), const SizedBox(width: 8)],
          Column(
            crossAxisAlignment: mine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              bubble,
              if (time.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 3, left: 4, right: 4),
                  child: Text(time, style: const TextStyle(color: Mk.hint, fontSize: 11.5, fontWeight: FontWeight.w500)),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
