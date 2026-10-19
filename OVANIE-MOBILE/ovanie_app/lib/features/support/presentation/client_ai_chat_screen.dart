import 'dart:async';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:open_filex/open_filex.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../payments/presentation/payment_ui.dart';
import '../data/support_repository.dart';
import '../domain/support_models.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _agentPhoto = 'assets/images/checkout/support_agent.jpg';

/// Conversation avec l'assistante OVANIE (IA), relayée à un conseiller
/// humain du support quand la demande le nécessite.
class ClientAiChatScreen extends StatefulWidget {
  const ClientAiChatScreen({super.key, this.orderReference, this.initialMessage});

  final String? orderReference;
  final String? initialMessage;

  @override
  State<ClientAiChatScreen> createState() => _ClientAiChatScreenState();
}

class _ChatMessage {
  _ChatMessage(this.sender, this.body, this.at, {this.attachments = const []});

  final String sender;
  final String body;
  final DateTime at;
  final List<SupportAttachment> attachments;

  bool get mine => sender == 'customer';
  bool get system => sender == 'system';
}

class _ClientAiChatScreenState extends State<ClientAiChatScreen> {
  static const _suggestions = [
    'Où en est ma commande ?',
    'Ma livraison est en retard',
    'Je veux retourner un article',
    'Problème de paiement',
    'Modifier mon adresse de livraison',
  ];

  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _messages = <_ChatMessage>[];
  String? _token;
  String _agent = 'N’Nan';
  String? _ticket;
  bool _human = false;
  bool _closed = false;
  bool _loading = true;
  bool _typing = false;
  bool _uploading = false;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    if (widget.initialMessage != null) _input.text = widget.initialMessage!;
    _input.addListener(() => setState(() {}));
    _resume();
  }

  @override
  void dispose() {
    _poll?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _resume() async {
    try {
      final r = await ApiClient.dio.get('/support/chat/latest', queryParameters: {'source_app': 'client_mobile'});
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
              attachments: [
                for (final file in (m['attachments'] as List? ?? const []).whereType<Map>())
                  SupportAttachment(
                    name: '${file['name'] ?? 'Fichier'}',
                    mime: '${file['mime'] ?? ''}',
                    size: int.tryParse('${file['size'] ?? ''}'),
                    url: '',
                    downloadEndpoint: '/${file['path'] ?? ''}',
                  ),
              ],
            ),
          ),
        );
    });
    if (_messages.length != before) _toBottom();
    _schedulePoll();
  }

  void _schedulePoll() {
    _poll?.cancel();
    if (_human && !_closed && _token != null) {
      _poll = Timer(const Duration(seconds: 8), () => _refresh().catchError((_) {}));
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
          ? await ApiClient.dio.post(
              '/support/chat/start',
              data: {...payload, 'source_app': 'client_mobile', 'service': 'client', 'channel': 'chat'},
            )
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
      _schedulePoll();
    } catch (e) {
      if (!mounted) return;
      setState(() => _messages.removeLast());
      _input.text = text;
      showOvanieToast(context, ApiClient.friendlyError(e), error: true);
    } finally {
      if (mounted) setState(() => _typing = false);
      _toBottom();
    }
  }

  /// Joint une photo ou un PDF (5 Mo max) à la conversation en cours.
  Future<void> _attach() async {
    if (_token == null || _uploading) return;
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    final path = result?.files.single.path;
    if (path == null || !mounted) return;
    setState(() => _uploading = true);
    try {
      final r = await ApiClient.dio.post(
        '/support/chat/$_token/attachments',
        data: FormData.fromMap({'file': await MultipartFile.fromFile(path)}),
      );
      ApiClient.ensureSuccess(r);
      await _refresh();
    } catch (e) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(e), error: true);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _openAttachment(SupportAttachment attachment) async {
    try {
      final file = await const SupportRepository().downloadAttachment(attachment);
      await OpenFilex.open(file.path);
    } catch (e) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(e), error: true);
    }
  }

  void _newConversation() {
    _poll?.cancel();
    setState(() {
      _token = null;
      _ticket = null;
      _human = false;
      _closed = false;
      _messages.clear();
    });
  }

  void _toBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(
          _scroll.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  String _time(DateTime d) => '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';

  /// Éléments de la conversation : messages, puis l'encart « relais » placé
  /// juste après le premier message du client quand un conseiller est saisi.
  List<Widget> _items() {
    final items = <Widget>[];
    var relayShown = false;
    for (final message in _messages) {
      items.add(message.system ? _SystemNotice(text: message.body, time: _time(message.at)) : _bubble(message));
      if (_human && !relayShown && message.mine) {
        relayShown = true;
        items.add(
          _RelayCard(
            title: _ticket == null
                ? 'Un conseiller OVANIE prend le relais.'
                : 'Dossier ${_ticket!.replaceFirst('#', '')} transmis au support.',
          ),
        );
      }
    }
    if (_typing && !_closed) items.add(const _TypingRow());
    if (_closed) {
      items.add(
        Container(
          margin: const EdgeInsets.only(top: 14),
          padding: const EdgeInsets.symmetric(vertical: 12),
          alignment: Alignment.center,
          decoration: BoxDecoration(color: const Color(0xFFE9ECF1), borderRadius: BorderRadius.circular(12)),
          child: const Text('Conversation clôturée', style: TextStyle(color: Color(0xFF5E6B80), fontSize: 14)),
        ),
      );
    }
    return items;
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: const Color(0xFFF6F8FB),
        body: Column(
          children: [
            _header(context),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
                  : _messages.isEmpty
                  ? _welcome()
                  : ListView(
                      controller: _scroll,
                      padding: const EdgeInsets.fromLTRB(12, 14, 12, 12),
                      children: _items(),
                    ),
            ),
            _closed ? _closedBar() : _composer(),
          ],
        ),
      ),
    );
  }

  Widget _header(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return Container(
      padding: EdgeInsets.fromLTRB(4, top + 6, 8, 10),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [BoxShadow(color: Color(0x0F0A1B3D), blurRadius: 8, offset: Offset(0, 2))],
      ),
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.maybePop(context),
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
          ),
          const _AgentAvatar(size: 44, dotSize: 12),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _agent,
                  style: const TextStyle(color: _navy, fontSize: 17, fontWeight: FontWeight.w800),
                ),
                Text(
                  _typing ? 'écrit…' : (_human ? 'Support OVANIE · conseiller notifié' : 'Assistante support OVANIE'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: _muted, fontSize: 13),
                ),
              ],
            ),
          ),
          if (_token != null)
            IconButton(
              tooltip: 'Nouvelle conversation',
              onPressed: _newConversation,
              icon: const Icon(Icons.edit_square, color: _navy, size: 26),
            ),
        ],
      ),
    );
  }

  Widget _welcome() => ListView(
    padding: const EdgeInsets.fromLTRB(12, 56, 12, 20),
    children: [
      const Center(child: _AgentAvatar(size: 128, dotSize: 26)),
      const SizedBox(height: 18),
      Text(
        'Bonjour, je suis $_agent',
        textAlign: TextAlign.center,
        style: const TextStyle(color: _navy, fontSize: 23, fontWeight: FontWeight.w800, letterSpacing: -0.4),
      ),
      const SizedBox(height: 8),
      const Text(
        'Posez votre question. Si besoin, je transmets votre dossier à l’équipe support OVANIE.',
        textAlign: TextAlign.center,
        style: TextStyle(color: _muted, fontSize: 14.5, height: 1.45),
      ),
      const SizedBox(height: 24),
      Wrap(
        alignment: WrapAlignment.center,
        spacing: 6,
        runSpacing: 10,
        children: [
          for (final s in _suggestions)
            Material(
              color: Colors.white,
              shape: const StadiumBorder(side: BorderSide(color: OvanieColors.orange, width: 1.2)),
              child: InkWell(
                customBorder: const StadiumBorder(),
                onTap: () => _send(s),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                  child: Text(
                    s,
                    style: const TextStyle(color: _navy, fontSize: 12.5, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ),
        ],
      ),
    ],
  );

  Widget _bubble(_ChatMessage m) {
    final human = m.sender == 'human';
    final width = MediaQuery.sizeOf(context).width;
    final bubble = Container(
      constraints: BoxConstraints(maxWidth: width * (m.mine ? .74 : .68)),
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      decoration: BoxDecoration(
        color: m.mine ? OvanieColors.navy : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: m.mine ? null : Border.all(color: const Color(0xFFE3E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (m.body.trim().isNotEmpty)
            Text(m.body, style: TextStyle(color: m.mine ? Colors.white : _navy, fontSize: 14.5, height: 1.42)),
          for (final file in m.attachments)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: InkWell(
                onTap: () => _openAttachment(file),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      file.mime.contains('pdf') ? Icons.picture_as_pdf_outlined : Icons.image_outlined,
                      color: m.mine ? Colors.white : OvanieColors.orange,
                      size: 20,
                    ),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(
                        file.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: m.mine ? Colors.white : _navy,
                          fontSize: 13,
                          decoration: TextDecoration.underline,
                          decorationColor: m.mine ? Colors.white : _navy,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
    final time = Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Text(_time(m.at), style: const TextStyle(color: _muted, fontSize: 11.5)),
    );
    if (m.mine) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [bubble, time]),
      );
    }
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          human ? const _AdvisorAvatar() : const _AgentAvatar(size: 40, dotSize: 0),
          const SizedBox(width: 8),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (human)
                  const Padding(
                    padding: EdgeInsets.only(left: 2, bottom: 4),
                    child: Text(
                      'Conseiller OVANIE',
                      style: TextStyle(color: Color(0xFF4A586E), fontSize: 12.5, fontWeight: FontWeight.w600),
                    ),
                  ),
                bubble,
                time,
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _composer() => SafeArea(
    top: false,
    child: Container(
      padding: const EdgeInsets.fromLTRB(6, 8, 10, 8),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          // Les pièces jointes s'ajoutent à une conversation déjà ouverte.
          IconButton(
            onPressed: _token == null || _uploading ? null : _attach,
            tooltip: 'Joindre une photo ou un PDF',
            icon: _uploading
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: OvanieColors.orange),
                  )
                : Icon(Icons.attach_file_rounded, color: _token == null ? const Color(0xFFC5CCD6) : _muted),
          ),
          Expanded(
            child: TextField(
              controller: _input,
              minLines: 1,
              maxLines: 5,
              textCapitalization: TextCapitalization.sentences,
              style: const TextStyle(fontSize: 15, color: _navy),
              decoration: InputDecoration(
                hintText: 'Écrire un message',
                hintStyle: const TextStyle(color: Color(0xFF8A96A8)),
                filled: true,
                fillColor: const Color(0xFFF0F2F5),
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
              ),
            ),
          ),
          const SizedBox(width: 8),
          SizedBox.square(
            dimension: 46,
            child: IconButton.filled(
              onPressed: _typing || _input.text.trim().isEmpty ? null : _send,
              style: IconButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                disabledBackgroundColor: const Color(0xFFFFC9A6),
              ),
              tooltip: 'Envoyer',
              icon: const Icon(Icons.send_rounded, color: Colors.white, size: 21),
            ),
          ),
        ],
      ),
    ),
  );

  Widget _closedBar() => SafeArea(
    top: false,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
      child: SizedBox(
        width: double.infinity,
        height: 50,
        child: FilledButton(
          onPressed: _newConversation,
          style: FilledButton.styleFrom(
            backgroundColor: OvanieColors.orange,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          child: const Text('Nouvelle conversation', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        ),
      ),
    ),
  );
}

class _AgentAvatar extends StatelessWidget {
  const _AgentAvatar({required this.size, required this.dotSize});

  final double size;
  final double dotSize;

  @override
  Widget build(BuildContext context) {
    return SizedBox.square(
      dimension: size,
      child: Stack(
        children: [
          ClipOval(
            child: Image.asset(_agentPhoto, width: size, height: size, fit: BoxFit.cover),
          ),
          if (dotSize > 0)
            Positioned(
              right: size * 0.02,
              bottom: size * 0.04,
              child: Container(
                width: dotSize,
                height: dotSize,
                decoration: BoxDecoration(
                  color: const Color(0xFF16A34A),
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: dotSize > 16 ? 4 : 2),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _AdvisorAvatar extends StatelessWidget {
  const _AdvisorAvatar();

  @override
  Widget build(BuildContext context) => Container(
    width: 40,
    height: 40,
    decoration: const BoxDecoration(color: Color(0xFFE9EEF6), shape: BoxShape.circle),
    child: const Icon(Icons.headset_mic_rounded, color: _navy, size: 22),
  );
}

class _RelayCard extends StatelessWidget {
  const _RelayCard({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 14),
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      decoration: BoxDecoration(color: const Color(0xFFE9ECF1), borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          const Icon(Icons.headset_mic_outlined, color: Color(0xFF4A586E), size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: Color(0xFF3B4A60), fontSize: 13.5)),
                const Text('La réponse s’affichera ici.', style: TextStyle(color: Color(0xFF6B7890), fontSize: 13)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SystemNotice extends StatelessWidget {
  const _SystemNotice({required this.text, required this.time});

  final String text;
  final String time;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
            decoration: BoxDecoration(color: const Color(0xFFE9ECF1), borderRadius: BorderRadius.circular(12)),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.headset_mic_outlined, color: Color(0xFF4A586E), size: 20),
                const SizedBox(width: 10),
                Flexible(
                  child: Text(text, style: const TextStyle(color: Color(0xFF3B4A60), fontSize: 13.5)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 4),
          Text(time, style: const TextStyle(color: _muted, fontSize: 11.5)),
        ],
      ),
    );
  }
}

/// « … » animé pendant que l'assistante rédige sa réponse.
class _TypingRow extends StatefulWidget {
  const _TypingRow();

  @override
  State<_TypingRow> createState() => _TypingRowState();
}

class _TypingRowState extends State<_TypingRow> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          const _AgentAvatar(size: 40, dotSize: 0),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(color: const Color(0xFFE9ECF1), borderRadius: BorderRadius.circular(18)),
            child: AnimatedBuilder(
              animation: _controller,
              builder: (context, _) => Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  for (var i = 0; i < 3; i++)
                    Container(
                      width: 8,
                      height: 8,
                      margin: EdgeInsets.only(right: i < 2 ? 5 : 0),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Color.lerp(
                          const Color(0xFFB7C0CC),
                          const Color(0xFF5E6B80),
                          ((_controller.value * 3 - i) % 3 < 1) ? 1 : 0,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
