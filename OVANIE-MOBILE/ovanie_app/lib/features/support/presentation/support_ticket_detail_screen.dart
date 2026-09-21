import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../data/support_repository.dart';
import '../domain/support_models.dart';

class SupportTicketDetailScreen extends StatefulWidget {
  final int ticketId;
  const SupportTicketDetailScreen({super.key, required this.ticketId});
  @override
  State<SupportTicketDetailScreen> createState() => _SupportTicketDetailScreenState();
}

class _SupportTicketDetailScreenState extends State<SupportTicketDetailScreen> {
  final _repository = const SupportRepository();
  final _message = TextEditingController();
  SupportTicketDetail? _detail;
  List<String> _attachments = const [];
  bool _loading = true;
  bool _sending = false;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  @override
  void dispose() { _message.dispose(); super.dispose(); }

  Future<void> _load() async {
    try {
      final detail = await _repository.ticket(widget.ticketId);
      if (!mounted) return;
      setState(() { _detail = detail; _loading = false; _error = null; });
    } catch (error) {
      if (!mounted) return;
      setState(() { _loading = false; _error = ApiClient.friendlyError(error); });
    }
  }

  Future<void> _pick() async {
    final result = await FilePicker.pickFiles(
      allowMultiple: true,
      withData: false,
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'txt'],
    );
    if (result == null || !mounted) return;
    final paths = result.files.map((file) => file.path).whereType<String>().where((path) => path.isNotEmpty);
    setState(() => _attachments = <String>{..._attachments, ...paths}.take(4).toList(growable: false));
  }

  Future<void> _send() async {
    final text = _message.text.trim();
    if ((text.isEmpty && _attachments.isEmpty) || _sending) return;
    setState(() => _sending = true);
    try {
      await _repository.replyTicket(widget.ticketId, text, attachmentPaths: _attachments);
      _message.clear();
      if (mounted) setState(() => _attachments = const []);
      await _load();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _openAttachment(SupportAttachment attachment) async {
    try {
      if (attachment.downloadEndpoint.isNotEmpty) {
        final file = await _repository.downloadAttachment(attachment);
        await OpenFilex.open(file.path);
        return;
      }
      if (attachment.url.isNotEmpty) {
        final opened = await ExternalUrlLauncher.open(attachment.url);
        if (!opened && mounted) throw const OvanieApiException('Cette pièce jointe ne peut pas être ouverte sur ce téléphone.');
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.friendlyError(error))));
    }
  }

  String _date(DateTime? value) {
    if (value == null) return '';
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(value.day)}/${two(value.month)}/${value.year} ${two(value.hour)}:${two(value.minute)}';
  }

  String _name(String path) => path.replaceAll('\\', '/').split('/').last;

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    final closed = detail != null && ['closed', 'cancelled'].contains(detail.summary.status);
    return Scaffold(
      appBar: AppBar(title: Text(detail?.summary.reference.isNotEmpty == true ? detail!.summary.reference : 'Ticket support')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
              : detail == null
                  ? const SizedBox.shrink()
                  : Column(
                      children: [
                        Expanded(
                          child: RefreshIndicator(
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.all(16),
                              children: [
                                Card(
                                  child: Padding(
                                    padding: const EdgeInsets.all(15),
                                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                      LayoutBuilder(
                                        builder: (context, constraints) {
                                          if (constraints.maxWidth < 380) {
                                            return Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(detail.summary.subject, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17)),
                                                const SizedBox(height: 8),
                                                _StatusChip(status: detail.summary.status),
                                              ],
                                            );
                                          }
                                          return Row(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Expanded(child: Text(detail.summary.subject, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17))),
                                              const SizedBox(width: 8),
                                              _StatusChip(status: detail.summary.status),
                                            ],
                                          );
                                        },
                                      ),
                                      const SizedBox(height: 7),
                                      Text('Catégorie : ${detail.summary.category}', style: const TextStyle(color: OvanieColors.muted)),
                                      if (detail.orderNumber.isNotEmpty) Text('Commande : ${detail.orderNumber}'),
                                      if (detail.returnId != null) Text('Retour : ${detail.returnStatus}${detail.refundAmount != null ? ' • ${detail.refundAmount!.round()} FCFA remboursé(s)' : ''}'),
                                    ]),
                                  ),
                                ),
                                const SizedBox(height: 16),
                                const Text('Historique des échanges', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
                                const SizedBox(height: 10),
                                if (detail.messages.isEmpty)
                                  const Text('Aucun échange enregistré.')
                                else
                                  ...detail.messages.map((message) => _MessageBubble(message: message, dateLabel: _date(message.createdAt), onOpenAttachment: _openAttachment)),
                              ],
                            ),
                          ),
                        ),
                        if (!closed)
                          SafeArea(
                            top: false,
                            child: Container(
                              color: Colors.white,
                              padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
                              child: Column(mainAxisSize: MainAxisSize.min, children: [
                                if (_attachments.isNotEmpty)
                                  SizedBox(
                                    height: 42,
                                    child: ListView.separated(
                                      scrollDirection: Axis.horizontal,
                                      itemCount: _attachments.length,
                                      separatorBuilder: (_, __) => const SizedBox(width: 6),
                                      itemBuilder: (context, index) => InputChip(
                                        label: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 130), child: Text(_name(_attachments[index]), overflow: TextOverflow.ellipsis)),
                                        onDeleted: _sending ? null : () => setState(() => _attachments = [..._attachments]..removeAt(index)),
                                      ),
                                    ),
                                  ),
                                Row(children: [
                                  IconButton(onPressed: _sending ? null : _pick, tooltip: 'Joindre un fichier', icon: const Icon(Icons.attach_file_rounded, color: OvanieColors.blue)),
                                  Expanded(child: TextField(controller: _message, minLines: 1, maxLines: 4, decoration: const InputDecoration(hintText: 'Répondre au support OVANIE…'))),
                                  const SizedBox(width: 8),
                                  IconButton.filled(
                                    onPressed: _sending ? null : _send,
                                    icon: _sending ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_rounded),
                                  ),
                                ]),
                              ]),
                            ),
                          ),
                      ],
                    ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});
  @override
  Widget build(BuildContext context) => Chip(label: Text(status, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800)));
}

class _MessageBubble extends StatelessWidget {
  final SupportTicketMessageItem message;
  final String dateLabel;
  final Future<void> Function(SupportAttachment) onOpenAttachment;
  const _MessageBubble({required this.message, required this.dateLabel, required this.onOpenAttachment});

  @override
  Widget build(BuildContext context) {
    final client = message.authorType == 'client' || message.authorType == 'customer';
    return Align(
      alignment: client ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: const BoxConstraints(maxWidth: 340),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: client ? OvanieColors.blue.withValues(alpha: .09) : Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: OvanieColors.border)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(client ? 'Vous' : 'Support OVANIE', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: OvanieColors.blue)),
          if (message.body.isNotEmpty) ...[const SizedBox(height: 4), Text(message.body)],
          if (message.attachments.isNotEmpty) ...[
            const SizedBox(height: 8),
            ...message.attachments.map((attachment) => TextButton.icon(
                  onPressed: () => onOpenAttachment(attachment),
                  icon: const Icon(Icons.attach_file_rounded, size: 16),
                  label: Text(attachment.name.isEmpty ? 'Ouvrir la pièce jointe' : attachment.name, overflow: TextOverflow.ellipsis),
                )),
          ],
          if (dateLabel.isNotEmpty) Text(dateLabel, style: const TextStyle(fontSize: 10, color: OvanieColors.muted)),
        ]),
      ),
    );
  }
}
