import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/network/api_client.dart';
import '../../../app/theme.dart';

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
  _ChatMessage(this.sender, this.body, this.at);
  final String sender;
  final String body;
  final DateTime at;
  bool get mine => sender == 'customer';
}

class _ClientAiChatScreenState extends State<ClientAiChatScreen> {
  static const _navy = OvanieColors.navy;
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
  Timer? _poll;

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
        ..addAll(rows.map((m) => _ChatMessage(
              '${m['sender']}',
              '${m['body'] ?? ''}',
              DateTime.tryParse('${m['created_at']}')?.toLocal() ?? DateTime.now(),
            )));
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
      final payload = {
        'message': text,
        if (widget.orderReference != null) 'order_reference': widget.orderReference,
      };
      final r = _token == null
          ? await ApiClient.dio.post('/support/chat/start', data: {...payload, 'source_app': 'client_mobile', 'service': 'client', 'channel': 'chat'})
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
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(e))));
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

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: const Color(0xFFF3F5F9),
        body: Column(
          children: [
            _header(context),
            if (_human) _banner(),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
                  : _messages.isEmpty
                  ? _welcome()
                  : ListView.builder(
                      controller: _scroll,
                      padding: const EdgeInsets.fromLTRB(12, 14, 12, 12),
                      itemCount: _messages.length + (_typing ? 1 : 0),
                      itemBuilder: (context, i) => i == _messages.length ? _typingBubble() : _bubble(_messages[i]),
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
      color: _navy,
      padding: EdgeInsets.fromLTRB(4, top + 4, 4, 10),
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.maybePop(context),
            icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
          ),
          Stack(
            children: [
              const CircleAvatar(
                radius: 19,
                backgroundColor: OvanieColors.orange,
                child: Icon(Icons.support_agent_rounded, color: Colors.white, size: 21),
              ),
              Positioned(
                right: 0,
                bottom: 0,
                child: Container(
                  width: 11,
                  height: 11,
                  decoration: BoxDecoration(
                    color: const Color(0xFF22C55E),
                    shape: BoxShape.circle,
                    border: Border.all(color: _navy, width: 2),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_agent, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700)),
                Text(
                  _typing ? 'écrit…' : (_human ? 'Support OVANIE · conseiller notifié' : 'Assistante support OVANIE'),
                  style: const TextStyle(color: Color(0xFFBFD0EA), fontSize: 12.5),
                ),
              ],
            ),
          ),
          if (_token != null)
            IconButton(
              tooltip: 'Nouvelle conversation',
              onPressed: _newConversation,
              icon: const Icon(Icons.add_comment_outlined, color: Colors.white),
            ),
        ],
      ),
    );
  }

  Widget _banner() => Container(
        width: double.infinity,
        color: const Color(0xFFFFF4E5),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            const Icon(Icons.headset_mic_rounded, color: Color(0xFFB45309), size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                _ticket == null
                    ? 'Un conseiller OVANIE prend le relais. Sa réponse s’affichera ici.'
                    : 'Dossier $_ticket transmis au support. La réponse s’affichera ici.',
                style: const TextStyle(color: Color(0xFF92400E), fontSize: 13),
              ),
            ),
          ],
        ),
      );

  Widget _welcome() => ListView(
        padding: const EdgeInsets.fromLTRB(20, 36, 20, 20),
        children: [
          const Center(
            child: CircleAvatar(
              radius: 32,
              backgroundColor: Color(0xFFFFEEDC),
              child: Icon(Icons.support_agent_rounded, color: OvanieColors.orange, size: 34),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            'Bonjour, je suis $_agent',
            textAlign: TextAlign.center,
            style: const TextStyle(color: OvanieColors.navy, fontSize: 19, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 6),
          const Text(
            'Posez votre question. Si besoin, je transmets votre dossier à l’équipe support OVANIE.',
            textAlign: TextAlign.center,
            style: TextStyle(color: OvanieColors.muted, fontSize: 14, height: 1.4),
          ),
          const SizedBox(height: 24),
          for (final s in _suggestions)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => _send(s),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: OvanieColors.border),
                    ),
                    child: Row(
                      children: [
                        Expanded(child: Text(s, style: const TextStyle(color: OvanieColors.navy, fontSize: 14.5))),
                        const Icon(Icons.north_east_rounded, color: OvanieColors.muted, size: 17),
                      ],
                    ),
                  ),
                ),
              ),
            ),
        ],
      );

  Widget _bubble(_ChatMessage m) {
    final human = m.sender == 'human';
    return Align(
      alignment: m.mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .78),
        padding: const EdgeInsets.fromLTRB(13, 9, 13, 7),
        decoration: BoxDecoration(
          color: m.mine ? _navy : Colors.white,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(m.mine ? 16 : 4),
            bottomRight: Radius.circular(m.mine ? 4 : 16),
          ),
          border: m.mine ? null : Border.all(color: const Color(0xFFE3E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (human)
              const Padding(
                padding: EdgeInsets.only(bottom: 3),
                child: Text('Conseiller OVANIE', style: TextStyle(color: OvanieColors.orange, fontSize: 12, fontWeight: FontWeight.w700)),
              ),
            Text(m.body, style: TextStyle(color: m.mine ? Colors.white : OvanieColors.navy, fontSize: 14.5, height: 1.4)),
            const SizedBox(height: 3),
            Align(
              alignment: Alignment.bottomRight,
              child: Text(
                _time(m.at),
                style: TextStyle(color: m.mine ? const Color(0xFFBFD0EA) : OvanieColors.muted, fontSize: 11),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _typingBubble() => Align(
        alignment: Alignment.centerLeft,
        child: Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE3E8F0)),
          ),
          child: const SizedBox(
            width: 34,
            child: LinearProgressIndicator(minHeight: 3, color: OvanieColors.orange, backgroundColor: Color(0xFFFFE3C7)),
          ),
        ),
      );

  Widget _composer() => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFE3E8F0))),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: TextField(
                  controller: _input,
                  minLines: 1,
                  maxLines: 5,
                  textCapitalization: TextCapitalization.sentences,
                  style: const TextStyle(fontSize: 15),
                  decoration: InputDecoration(
                    hintText: 'Écrire un message',
                    filled: true,
                    fillColor: const Color(0xFFF3F5F9),
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(22), borderSide: BorderSide.none),
                  ),
                ),
              ),
              const SizedBox(width: 6),
              IconButton.filled(
                onPressed: _typing ? null : _send,
                style: IconButton.styleFrom(backgroundColor: OvanieColors.orange, fixedSize: const Size(46, 46)),
                icon: const Icon(Icons.send_rounded, color: Colors.white, size: 20),
              ),
            ],
          ),
        ),
      );

  Widget _closedBar() => SafeArea(
        top: false,
        child: Container(
          color: Colors.white,
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          child: Row(
            children: [
              const Expanded(child: Text('Conversation clôturée', style: TextStyle(color: OvanieColors.muted, fontSize: 14))),
              TextButton(onPressed: _newConversation, child: const Text('Nouvelle conversation')),
            ],
          ),
        ),
      );
}
