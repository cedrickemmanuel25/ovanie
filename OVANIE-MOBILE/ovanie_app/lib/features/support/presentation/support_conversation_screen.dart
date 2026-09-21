import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/support_repository.dart';
import '../domain/support_models.dart';

class SupportConversationScreen extends StatefulWidget {
  final String token;
  final String title;
  const SupportConversationScreen({super.key, required this.token, this.title = 'Conversation support'});
  @override
  State<SupportConversationScreen> createState() => _SupportConversationScreenState();
}

class _SupportConversationScreenState extends State<SupportConversationScreen> {
  final _repository = const SupportRepository();
  final _controller = TextEditingController();
  SupportConversationDetail? _detail;
  bool _loading = true;
  bool _sending = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final detail = await _repository.conversation(widget.token);
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      await _repository.sendConversationMessage(widget.token, text);
      _controller.clear();
      await _load();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    final closed = detail != null && ['resolved', 'closed'].contains(detail.status);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        bottom: detail?.requiresHuman == true
            ? const PreferredSize(
                preferredSize: Size.fromHeight(32),
                child: Padding(
                  padding: EdgeInsets.only(bottom: 8),
                  child: Text('Transmis à un agent humain', style: TextStyle(color: OvanieColors.orange, fontWeight: FontWeight.w800)),
                ),
              )
            : null,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
              : Column(children: [
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.all(14),
                      children: [
                        if ((detail?.ticketReference ?? '').isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Text('Ticket lié : ${detail!.ticketReference}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          ),
                        ...?detail?.messages.map((message) {
                          final customer = ['customer', 'client', 'user'].contains(message.sender);
                          return Align(
                            alignment: customer ? Alignment.centerRight : Alignment.centerLeft,
                            child: Container(
                              constraints: const BoxConstraints(maxWidth: 340),
                              margin: const EdgeInsets.only(bottom: 10),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: customer ? OvanieColors.blue.withValues(alpha: .10) : Colors.white,
                                border: Border.all(color: OvanieColors.border),
                                borderRadius: BorderRadius.circular(15),
                              ),
                              child: Text(message.body),
                            ),
                          );
                        }),
                      ],
                    ),
                  ),
                  if (!closed)
                    SafeArea(
                      top: false,
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        color: Colors.white,
                        child: Row(children: [
                          Expanded(child: TextField(controller: _controller, maxLines: 4, minLines: 1, decoration: const InputDecoration(hintText: 'Écrivez votre message…'))),
                          const SizedBox(width: 8),
                          IconButton.filled(onPressed: _sending ? null : _send, icon: const Icon(Icons.send_rounded)),
                        ]),
                      ),
                    ),
                ]),
    );
  }
}
