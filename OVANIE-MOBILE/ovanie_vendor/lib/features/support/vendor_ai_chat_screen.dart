import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/mk_dialogs.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../auth/vendor_session.dart';
import '../finance/data_ui.dart' show downloadVendorFile;
import '../finance/finance_ui.dart' show payoutStatusColor, payoutStatusLabel;
import '../finance/payout_detail_screen.dart';
import '../menu/menu_ui.dart' show menuApi, allMenuRows;

/// Conversation avec l'assistante OVANIE (IA), relayée à un conseiller
/// humain du support quand la demande le nécessite.
class VendorAiChatScreen extends StatefulWidget {
  const VendorAiChatScreen({super.key, this.orderReference, this.initialMessage});

  final String? orderReference;
  final String? initialMessage;

  @override
  State<VendorAiChatScreen> createState() => _VendorAiChatScreenState();
}

class _ChatFile {
  const _ChatFile(this.name, this.path, this.mime);
  final String name, path, mime;
  bool get pdf => mime.contains('pdf') || name.toLowerCase().endsWith('.pdf');
}

class _ChatMessage {
  _ChatMessage(this.sender, this.body, this.at, {this.files = const []});
  final String sender;
  final String body;
  final DateTime at;
  final List<_ChatFile> files;
  bool get mine => sender == 'customer';
}

class _VendorAiChatScreenState extends State<VendorAiChatScreen> {
  static const _blue = Color(0xFF1668E3);
  /// La question sur la livraison dépend du mode de la boutique : collecte
  /// OVANIE Logistics, ou livraison assurée par le vendeur.
  List<(String, IconData, bool)> get _suggestions => [
    ('Où en est mon versement ?', Icons.account_balance_wallet_outlined, false),
    ('Un client conteste une commande', Icons.balance_rounded, true),
    ('Je n’arrive pas à publier un produit', Icons.view_in_ar_outlined, false),
    (
      '${VendorSession.instance.shop?['logistics_type']}' == 'seller'
          ? 'Problème avec une de mes livraisons'
          : 'Problème avec une livraison OVANIE',
      Icons.local_shipping_outlined,
      true,
    ),
    ('Modifier mon compte Mobile Money', Icons.call_outlined, false),
  ];
  static final _payoutReference = RegExp(r'VRS-\d{8}-[A-Z0-9]+', caseSensitive: false);

  final _input = TextEditingController();
  final _inputFocus = FocusNode();
  final _scroll = ScrollController();
  final _messages = <_ChatMessage>[];
  String? _token;
  String _agent = 'N’Nan';
  String? _ticket;
  String? _callbackReference;
  DateTime? _callbackAt;
  bool _human = false;
  bool _closed = false;
  bool _loading = true;
  bool _typing = false;
  bool _uploading = false;
  Timer? _poll;

  /// Versements du vendeur, chargés la première fois qu'une référence VRS
  /// apparaît dans la conversation, pour afficher la fiche correspondante.
  Map<String, Map<String, dynamic>>? _payouts;
  bool _loadingPayouts = false;

  @override
  void initState() {
    super.initState();
    if (widget.initialMessage != null) _input.text = widget.initialMessage!;
    _resume();
  }

  @override
  void dispose() {
    _poll?.cancel();
    _input.dispose();
    _inputFocus.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _resume() async {
    try {
      final r = await ApiClient.dio.get('/support/chat/latest', queryParameters: {'source_app': 'vendor_mobile'});
      ApiClient.ensureSuccess(r);
      final data = Map<String, dynamic>.from(r.data as Map);
      if (data['exists'] == true) {
        _token = '${data['conversation_token']}';
        await _refresh();
      }
    } catch (_) {
      // Pas de conversation à reprendre : on démarre à vide.
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _refresh() async {
    if (_token == null) return;
    final r = await ApiClient.dio.get('/support/chat/$_token');
    ApiClient.ensureSuccess(r);
    final data = Map<String, dynamic>.from(r.data as Map);
    final rows = (data['messages'] as List? ?? const []).cast<Map>();
    if (!mounted) return;
    final before = _messages.length;
    setState(() {
      _agent = '${(data['agent'] as Map?)?['name'] ?? _agent}';
      _human = data['requires_human'] == true;
      _closed = ['resolved', 'closed'].contains('${data['status']}');
      _ticket = (data['ticket'] as Map?)?['reference']?.toString() ?? _ticket;
      _messages
        ..clear()
        ..addAll(
          rows.map(
            (m) => _ChatMessage(
              '${m['sender']}',
              '${m['body'] ?? ''}',
              DateTime.tryParse('${m['created_at']}')?.toLocal() ?? DateTime.now(),
              files: [
                for (final f in (m['attachments'] as List? ?? const []).whereType<Map>())
                  _ChatFile('${f['name'] ?? 'Fichier'}', '${f['path'] ?? ''}', '${f['mime'] ?? ''}'),
              ],
            ),
          ),
        );
    });
    _lookForPayouts();
    if (_messages.length != before) _toBottom();
    _schedulePoll();
  }

  void _schedulePoll() {
    _poll?.cancel();
    if (_human && !_closed && _token != null) {
      _poll = Timer(const Duration(seconds: 8), () => _refresh().catchError((_) {}));
    }
  }

  Future<void> _lookForPayouts() async {
    if (_payouts != null || _loadingPayouts) return;
    if (!_messages.any((m) => _payoutReference.hasMatch(m.body))) return;
    _loadingPayouts = true;
    try {
      final rows = await allMenuRows('payouts');
      if (!mounted) return;
      setState(() {
        _payouts = {
          for (final row in rows)
            if ('${row['reference'] ?? ''}'.isNotEmpty) '${row['reference']}'.toUpperCase(): row,
        };
      });
    } catch (_) {
      // Sans la liste, la conversation reste lisible ; seule la fiche manque.
    } finally {
      _loadingPayouts = false;
    }
  }

  Future<void> _send([String? preset]) async {
    final text = (preset ?? _input.text).trim();
    if (text.isEmpty || _typing) return;
    _input.clear();
    setState(() {
      _messages.add(_ChatMessage('customer', text, DateTime.now()));
      _typing = true;
    });
    _toBottom();
    try {
      final payload = {'message': text, if (widget.orderReference != null) 'order_reference': widget.orderReference};
      final r = _token == null
          ? await ApiClient.dio.post('/support/chat/start', data: {...payload, 'source_app': 'vendor_mobile', 'channel': 'chat'})
          : await ApiClient.dio.post('/support/chat/$_token/messages', data: payload);
      ApiClient.ensureSuccess(r);
      final data = Map<String, dynamic>.from(r.data as Map);
      _token ??= '${data['conversation_token']}';
      if (!mounted) return;
      setState(() {
        _agent = '${(data['agent'] as Map?)?['name'] ?? _agent}';
        _human = data['requires_human'] == true;
        _ticket = data['ticket_reference']?.toString() ?? _ticket;
        final reply = '${data['reply'] ?? ''}'.trim();
        if (reply.isNotEmpty) _messages.add(_ChatMessage('ai', reply, DateTime.now()));
      });
      _lookForPayouts();
      _schedulePoll();
    } catch (e) {
      if (!mounted) return;
      setState(() => _messages.removeLast());
      _input.text = text;
      mkToast(context, ApiClient.friendlyError(e), error: true);
    } finally {
      if (mounted) setState(() => _typing = false);
      _toBottom();
    }
  }

  void _newConversation() {
    _poll?.cancel();
    setState(() {
      _token = null;
      _ticket = null;
      _callbackReference = null;
      _human = false;
      _closed = false;
      _messages.clear();
    });
  }

  void _toBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
      }
    });
  }

  String _time(DateTime d) => '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';

  // ---- Pièce jointe ---------------------------------------------------------------
  Future<void> _attach() async {
    if (_uploading || _typing) return;
    if (_token == null) {
      mkToast(context, 'Posez d’abord votre question : le fichier sera joint à cette conversation.', plain: true);
      return;
    }
    final choice = await showMkSheet<String>(
      context,
      builder: (sheet) => MkSheetFrame(
        children: [
          const Text(
            'Joindre un fichier',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.navy, fontSize: 21, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          const Text(
            'Photo ou PDF, 5 Mo maximum. Le conseiller OVANIE pourra le consulter.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 16),
          MkBarButton(
            label: 'Prendre une photo',
            icon: Icons.photo_camera_outlined,
            filled: true,
            onTap: () => Navigator.pop(sheet, 'camera'),
          ),
          const SizedBox(height: 8),
          MkBarButton(label: 'Choisir dans la galerie', icon: Icons.image_outlined, onTap: () => Navigator.pop(sheet, 'gallery')),
          const SizedBox(height: 8),
          MkBarButton(
            label: 'Choisir un PDF',
            icon: Icons.picture_as_pdf_outlined,
            color: Mk.navy,
            onTap: () => Navigator.pop(sheet, 'pdf'),
          ),
        ],
      ),
    );
    if (choice == null || !mounted) return;
    try {
      File? file;
      if (choice == 'pdf') {
        final picked = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: const ['pdf']);
        final path = picked?.files.single.path;
        if (path != null) file = File(path);
      } else {
        final picked = await ImagePicker().pickImage(
          source: choice == 'camera' ? ImageSource.camera : ImageSource.gallery,
          maxWidth: 2200,
          imageQuality: 85,
        );
        if (picked != null) file = File(picked.path);
      }
      if (file == null || !mounted) return;
      setState(() => _uploading = true);
      final caption = _input.text.trim();
      final r = await ApiClient.dio.post(
        '/support/chat/$_token/attachments',
        data: FormData.fromMap({
          'file': await MultipartFile.fromFile(file.path, filename: file.uri.pathSegments.last),
          if (caption.isNotEmpty) 'message': caption,
        }),
      );
      ApiClient.ensureSuccess(r);
      if (caption.isNotEmpty) _input.clear();
      await _refresh();
    } catch (e) {
      if (mounted) mkToast(context, ApiClient.friendlyError(e), error: true);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  // ---- Demande de rappel --------------------------------------------------------
  Future<void> _askCallback() async {
    if (_token == null) {
      mkToast(context, 'Posez d’abord votre question : le rappel sera rattaché à cette conversation.', plain: true);
      return;
    }
    Map<String, dynamic> user = const {};
    try {
      user = Map<String, dynamic>.from((await menuApi('profile'))['user'] as Map? ?? const {});
    } catch (_) {
      // Les champs restent vides : le vendeur les saisit.
    }
    if (!mounted) return;
    final reference = await showMkSheet<String>(context, builder: (_) => _CallbackSheet(token: _token!, user: user));
    if (reference == null || !mounted) return;
    setState(() {
      _callbackReference = reference;
      _callbackAt = DateTime.now();
    });
    _toBottom();
  }

  @override
  Widget build(BuildContext context) {
    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: const Color(0xFFF3F6FB),
        body: Column(
          children: [
            _top(context),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator(color: Mk.orange))
                  : _messages.isEmpty
                  ? _welcome()
                  : ListView(
                      controller: _scroll,
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
                      children: [
                        for (var i = 0; i < _messages.length; i++) ...[
                          if (i == 0 || !_sameDay(_messages[i - 1].at, _messages[i].at)) _dayChip(_messages[i].at),
                          _bubble(_messages[i]),
                          ..._payoutCards(_messages[i]),
                        ],
                        if (_callbackReference != null) _callbackNotice(),
                        if (_typing) _typingBubble(),
                        if (_closed) _closedNotice(),
                      ],
                    ),
            ),
            _composer(),
          ],
        ),
      ),
    );
  }

  bool _sameDay(DateTime a, DateTime b) => a.year == b.year && a.month == b.month && a.day == b.day;

  Widget _dayChip(DateTime at) {
    final now = DateTime.now();
    final diff = DateTime(now.year, now.month, now.day).difference(DateTime(at.year, at.month, at.day)).inDays;
    final label = diff == 0
        ? 'Aujourd’hui'
        : diff == 1
        ? 'Hier'
        : '${at.day.toString().padLeft(2, '0')}/${at.month.toString().padLeft(2, '0')}/${at.year}';
    return Center(
      child: Container(
        margin: const EdgeInsets.only(bottom: 12, top: 2),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
        decoration: BoxDecoration(color: const Color(0xFFE6EBF3), borderRadius: BorderRadius.circular(99)),
        child: Text(
          label,
          style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
        ),
      ),
    );
  }

  Widget _avatar(double size) => ClipOval(
    child: Image.asset('assets/images/mockups/nnan.jpg', width: size, height: size, fit: BoxFit.cover, excludeFromSemantics: true),
  );

  /// En-tête blanc arrondi ; quand un conseiller a repris la conversation, le
  /// bandeau bleu clair du dossier dépasse dessous.
  Widget _top(BuildContext context) {
    final header = _header(context);
    if (!_human) return header;
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        color: Color(0xFFDDEAFD),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(18)),
      ),
      child: Column(
        children: [
          header,
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
            child: Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: const BoxDecoration(color: Color(0xFFC6DAFA), shape: BoxShape.circle),
                  child: const Icon(Icons.support_agent_rounded, color: Mk.blue, size: 22),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text.rich(
                    TextSpan(
                      children: _ticket == null
                          ? const [
                              TextSpan(
                                text: 'Un conseiller OVANIE',
                                style: TextStyle(fontWeight: FontWeight.w800),
                              ),
                              TextSpan(text: ' prend le relais.\nSa réponse s’affichera ici.'),
                            ]
                          : [
                              TextSpan(
                                text: 'Dossier $_ticket',
                                style: const TextStyle(fontWeight: FontWeight.w800),
                              ),
                              const TextSpan(text: ' transmis au support.\nLa réponse s’affichera ici.'),
                            ],
                    ),
                    style: const TextStyle(color: Mk.navy, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _header(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    final empty = _messages.isEmpty && !_loading;
    return Container(
      padding: EdgeInsets.fromLTRB(4, top + 6, 6, 10),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(18)),
        boxShadow: [BoxShadow(color: Color(0x14062962), blurRadius: 14, offset: Offset(0, 4))],
      ),
      child: Row(
        children: [
          IconButton(
            tooltip: 'Retour',
            onPressed: () => Navigator.maybePop(context),
            icon: const Icon(Icons.arrow_back_rounded, color: Mk.navy, size: 26),
          ),
          _avatar(54),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _agent,
                  style: const TextStyle(color: Mk.navy, fontSize: 19, height: 1.2, fontWeight: FontWeight.w800),
                ),
                if (!_closed)
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text(
                      _human ? 'Conseiller OVANIE' : 'Assistance vendeur OVANIE',
                      style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                    ),
                  ),
                const SizedBox(height: 1),
                Row(
                  children: [
                    Container(
                      width: 9,
                      height: 9,
                      decoration: BoxDecoration(color: _closed ? Mk.hint : const Color(0xFF22A45D), shape: BoxShape.circle),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      _typing ? 'écrit…' : (_closed ? 'conversation clôturée' : 'en ligne'),
                      style: TextStyle(
                        color: _closed ? Mk.muted : const Color(0xFF16803C),
                        fontSize: 12.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (empty)
            IconButton(
              tooltip: 'Nouvelle conversation',
              onPressed: _inputFocus.requestFocus,
              icon: const Icon(Icons.add_comment_outlined, color: Mk.navy, size: 26),
            ),
          IconButton(
            tooltip: 'Être rappelé',
            onPressed: _askCallback,
            icon: const Icon(Icons.call_outlined, color: Mk.navy, size: 27),
          ),
        ],
      ),
    );
  }

  Widget _welcome() => ListView(
    padding: const EdgeInsets.fromLTRB(16, 22, 16, 16),
    children: [
      Center(
        child: Container(
          padding: const EdgeInsets.all(4),
          decoration: const BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            boxShadow: [BoxShadow(color: Color(0x1A062962), blurRadius: 18, offset: Offset(0, 6))],
          ),
          child: _avatar(116),
        ),
      ),
      const SizedBox(height: 18),
      FittedBox(
        fit: BoxFit.scaleDown,
        child: Text(
          'Bonjour, je suis $_agent',
          style: const TextStyle(color: Mk.navy, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -.4),
        ),
      ),
      const SizedBox(height: 8),
      const Text(
        'Posez votre question sur votre boutique.\nSi besoin, je transmets votre dossier à l’équipe support OVANIE.',
        textAlign: TextAlign.center,
        style: TextStyle(color: Mk.muted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
      ),
      const SizedBox(height: 20),
      for (final s in _suggestions)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => _send(s.$1),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                child: Row(
                  children: [
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: s.$3 ? const Color(0xFFFFEFE5) : const Color(0xFFE6F0FE),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(s.$2, color: s.$3 ? Mk.orange : _blue, size: 21),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        s.$1,
                        style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w500),
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 22),
                  ],
                ),
              ),
            ),
          ),
        ),
    ],
  );

  Widget _fileChip(_ChatFile f, bool mine) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: Material(
      color: mine ? const Color(0x24FFFFFF) : const Color(0xFFF1F4F9),
      borderRadius: BorderRadius.circular(10),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: f.path.isEmpty ? null : () => downloadVendorFile(context, '/${f.path}', f.name, done: 'Fichier enregistré.'),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                f.pdf ? Icons.picture_as_pdf_outlined : Icons.image_outlined,
                color: mine ? Colors.white : Mk.icon,
                size: 20,
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  f.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: mine ? Colors.white : Mk.navy, fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );

  Widget _bubble(_ChatMessage m) {
    final human = m.sender == 'human';
    final maxWidth = MediaQuery.sizeOf(context).width * .72;
    final bubble = Container(
      constraints: BoxConstraints(maxWidth: maxWidth),
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 11),
      decoration: BoxDecoration(
        color: m.mine ? const Color(0xFF0A2A66) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: m.mine ? null : const [BoxShadow(color: Color(0x0F062962), blurRadius: 8, offset: Offset(0, 2))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            m.body,
            style: TextStyle(color: m.mine ? Colors.white : Mk.navy, fontSize: 14.5, height: 1.4, fontWeight: FontWeight.w500),
          ),
          for (final f in m.files) _fileChip(f, m.mine),
        ],
      ),
    );
    final time = Padding(
      padding: const EdgeInsets.only(top: 4, left: 6, right: 2),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            _time(m.at),
            style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
          ),
          if (m.mine) ...[
            const SizedBox(width: 6),
            const Icon(Icons.done_all_rounded, color: _blue, size: 17),
            const SizedBox(width: 3),
            const Text(
              'Envoyé',
              style: TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ],
        ],
      ),
    );
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        mainAxisAlignment: m.mine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (!m.mine) ...[
            Padding(padding: EdgeInsets.only(top: human ? 22 : 0), child: _avatar(40)),
            const SizedBox(width: 10),
          ],
          Column(
            crossAxisAlignment: m.mine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              if (human)
                Container(
                  margin: const EdgeInsets.only(bottom: 4),
                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
                  decoration: BoxDecoration(color: const Color(0xFFFFEFE5), borderRadius: BorderRadius.circular(99)),
                  child: const Text(
                    'Conseiller OVANIE',
                    style: TextStyle(color: Color(0xFFE85400), fontSize: 11.5, fontWeight: FontWeight.w600),
                  ),
                ),
              bubble,
              time,
            ],
          ),
        ],
      ),
    );
  }

  /// Fiche du versement cité dans une réponse (référence VRS reconnue).
  List<Widget> _payoutCards(_ChatMessage m) {
    final payouts = _payouts;
    if (payouts == null || m.mine) return const [];
    final seen = <String>{};
    return [
      for (final match in _payoutReference.allMatches(m.body))
        if (payouts[match.group(0)!.toUpperCase()] case final payout? when seen.add(match.group(0)!.toUpperCase()))
          _payoutCard(payout),
    ];
  }

  Widget _payoutCard(Map<String, dynamic> p) {
    final id = int.tryParse('${p['id']}');
    final amount = double.tryParse('${p['amount'] ?? p['payout_amount'] ?? 0}') ?? 0;
    final digits = amount.round().toString().replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ' ');
    final color = payoutStatusColor(p['status']);
    return Padding(
      padding: const EdgeInsets.only(left: 50, bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: id == null
              ? null
              : () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => PayoutDetailScreen(payoutId: id))),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(10, 10, 6, 10),
            child: Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(color: const Color(0xFFE6F0FE), borderRadius: BorderRadius.circular(12)),
                  child: const Icon(Icons.account_balance_wallet_outlined, color: _blue, size: 25),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${p['reference']}',
                        style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
                      ),
                      Text(
                        '$digits FCFA',
                        style: const TextStyle(color: Mk.navy, fontSize: 16, height: 1.3, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 3),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
                        decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(99)),
                        child: Text(
                          payoutStatusLabel(p['status']),
                          style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
                        ),
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
    );
  }

  Widget _typingBubble() => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(
      children: [
        _avatar(40),
        const SizedBox(width: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
          decoration: BoxDecoration(color: const Color(0xFFE6EBF3), borderRadius: BorderRadius.circular(99)),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const _TypingDots(),
              const SizedBox(width: 10),
              Text(
                '$_agent écrit…',
                style: const TextStyle(color: Mk.icon, fontSize: 13, fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _callbackNotice() => Padding(
    padding: const EdgeInsets.only(bottom: 12, left: 14, right: 14),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 14, 12),
          decoration: BoxDecoration(color: const Color(0xFFE4F5EA), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              const Icon(Icons.check_circle_rounded, color: Color(0xFF12894A), size: 30),
              const SizedBox(width: 12),
              Container(width: 1, height: 36, color: const Color(0xFFB9DFC8)),
              const SizedBox(width: 12),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    text: 'Votre demande de rappel a été enregistrée.\n',
                    children: [
                      TextSpan(
                        text: _callbackReference,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                  style: const TextStyle(color: Color(0xFF0B5E32), fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ),
        if (_callbackAt != null)
          Padding(
            padding: const EdgeInsets.only(top: 4, left: 72),
            child: Text(
              _time(_callbackAt!),
              style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ),
      ],
    ),
  );

  Widget _closedNotice() => Center(
    child: Container(
      margin: const EdgeInsets.only(top: 14, bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
      decoration: BoxDecoration(color: const Color(0xFFE6EBF3), borderRadius: BorderRadius.circular(99)),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.lock_rounded, color: Mk.icon, size: 18),
          SizedBox(width: 10),
          Text(
            'Cette conversation est clôturée.',
            style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    ),
  );

  Widget _composer() => SafeArea(
    top: false,
    child: Container(
      padding: _closed ? const EdgeInsets.fromLTRB(10, 10, 10, 10) : const EdgeInsets.fromLTRB(2, 8, 10, 8),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
      ),
      child: _closed
          ? MkBarButton(label: 'Nouvelle conversation', filled: true, height: 50, onTap: _newConversation)
          : Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                SizedBox(
                  width: 46,
                  height: 48,
                  child: _uploading
                      ? const Center(
                          child: SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2.2, color: Mk.orange),
                          ),
                        )
                      : IconButton(
                          tooltip: 'Joindre un fichier',
                          onPressed: _attach,
                          icon: Transform.rotate(
                            angle: .7,
                            child: const Icon(Icons.attach_file_rounded, color: Mk.icon, size: 26),
                          ),
                        ),
                ),
                Expanded(
                  child: TextField(
                    controller: _input,
                    focusNode: _inputFocus,
                    minLines: 1,
                    maxLines: 5,
                    textCapitalization: TextCapitalization.sentences,
                    inputFormatters: [LengthLimitingTextInputFormatter(2000)],
                    style: const TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w500),
                    decoration: InputDecoration(
                      hintText: 'Écrivez votre message…',
                      hintStyle: const TextStyle(color: Mk.hint, fontSize: 15, fontWeight: FontWeight.w400),
                      filled: true,
                      fillColor: const Color(0xFFEFF2F7),
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(26), borderSide: BorderSide.none),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(26), borderSide: BorderSide.none),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(26),
                        borderSide: const BorderSide(color: Mk.border),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Material(
                  color: _typing ? const Color(0xFFC5CEDE) : Mk.orange,
                  shape: const CircleBorder(),
                  clipBehavior: Clip.antiAlias,
                  child: InkWell(
                    onTap: _typing ? null : _send,
                    child: SizedBox(
                      width: 48,
                      height: 48,
                      child: Transform.rotate(
                        angle: -.45,
                        child: const Icon(Icons.send_rounded, color: Colors.white, size: 22, semanticLabel: 'Envoyer'),
                      ),
                    ),
                  ),
                ),
              ],
            ),
    ),
  );
}

/// Trois points qui s'animent pendant que l'assistante rédige.
class _TypingDots extends StatefulWidget {
  const _TypingDots();
  @override
  State<_TypingDots> createState() => _TypingDotsState();
}

class _TypingDotsState extends State<_TypingDots> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    builder: (_, __) => Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < 3; i++)
          Container(
            width: 9,
            height: 9,
            margin: const EdgeInsets.symmetric(horizontal: 2),
            decoration: BoxDecoration(
              color: Color.lerp(
                const Color(0xFFB9CCF2),
                const Color(0xFF1668E3),
                (1 - ((_c.value * 3 - i) % 3).clamp(0, 1)).toDouble(),
              ),
              shape: BoxShape.circle,
            ),
          ),
      ],
    ),
  );
}

/// Feuille « Être rappelé » : téléphone, e-mail et motif, envoyés au support.
class _CallbackSheet extends StatefulWidget {
  const _CallbackSheet({required this.token, required this.user});
  final String token;
  final Map<String, dynamic> user;
  @override
  State<_CallbackSheet> createState() => _CallbackSheetState();
}

class _CallbackSheetState extends State<_CallbackSheet> {
  final _form = GlobalKey<FormState>();
  late final _phone = TextEditingController(text: '${widget.user['phone'] ?? ''}');
  late final _email = TextEditingController(text: '${widget.user['email'] ?? ''}');
  final _reason = TextEditingController(text: 'Rappel demandé depuis la conversation en ligne.');
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _phone.dispose();
    _email.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final r = await ApiClient.dio.post(
        '/support/chat/${widget.token}/callback',
        data: {
          'phone': _phone.text.trim(),
          if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
          if (_reason.text.trim().isNotEmpty) 'reason': _reason.text.trim(),
          if ('${widget.user['name'] ?? ''}'.trim().isNotEmpty) 'name': '${widget.user['name']}'.trim(),
        },
      );
      ApiClient.ensureSuccess(r);
      final data = Map<String, dynamic>.from(r.data as Map);
      if (mounted) Navigator.pop(context, '${data['reference'] ?? ''}');
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => MkSheetFrame(
    children: [
      const Center(child: MkIconDisc(Icons.call_outlined, color: Color(0xFF1668E3))),
      const SizedBox(height: 12),
      const Text(
        'Être rappelé',
        textAlign: TextAlign.center,
        style: TextStyle(color: Mk.navy, fontSize: 22, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 4),
      const Text(
        'Un conseiller OVANIE vous rappelle au numéro indiqué.',
        textAlign: TextAlign.center,
        style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
      ),
      const SizedBox(height: 14),
      Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const MkLabel('Téléphone', required: true),
            const SizedBox(height: 6),
            TextFormField(
              controller: _phone,
              enabled: !_sending,
              keyboardType: TextInputType.phone,
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ]')), LengthLimitingTextInputFormatter(20)],
              style: mkFieldText,
              decoration: mkInput(hint: '07 00 00 00 00', icon: Icons.call_outlined),
              validator: (v) {
                final digits = (v ?? '').replaceAll(RegExp(r'\D'), '');
                final local = digits.length == 13 && digits.startsWith('225') ? digits.substring(3) : digits;
                return local.length == 10 ? null : 'Le numéro doit contenir 10 chiffres.';
              },
            ),
            const SizedBox(height: 12),
            const MkLabel('Email'),
            const SizedBox(height: 6),
            TextFormField(
              controller: _email,
              enabled: !_sending,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              style: mkFieldText,
              decoration: mkInput(hint: 'vous@exemple.ci', icon: Icons.mail_outline_rounded),
              validator: (v) {
                final text = (v ?? '').trim();
                if (text.isEmpty) return null;
                return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(text) ? null : 'Adresse e-mail invalide.';
              },
            ),
            const SizedBox(height: 12),
            const MkLabel('Motif'),
            const SizedBox(height: 6),
            TextFormField(
              controller: _reason,
              enabled: !_sending,
              minLines: 2,
              maxLines: 4,
              inputFormatters: [LengthLimitingTextInputFormatter(3000)],
              style: mkFieldText.copyWith(height: 1.4),
              decoration: mkInput(hint: 'Expliquez brièvement votre besoin'),
            ),
          ],
        ),
      ),
      if (_error != null) ...[const SizedBox(height: 10), MkNotice(_error!)],
      const SizedBox(height: 16),
      MkBarButton(label: 'Demander un rappel', filled: true, loading: _sending, onTap: _sending ? null : _send),
    ],
  );
}
