import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
    final d = value.toLocal();
    return '${two(d.day)}/${two(d.month)} · ${two(d.hour)}:${two(d.minute)}';
  }

  String _name(String path) => path.replaceAll('\\', '/').split('/').last;

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    final closed = detail != null && ['closed', 'cancelled'].contains(detail.summary.status);
    final top = MediaQuery.paddingOf(context).top;
    final context0 = detail == null
        ? ''
        : [
            supportCategoryLabel(detail.summary.category),
            if (detail.orderNumber.isNotEmpty) 'Commande ${detail.orderNumber}',
            if (detail.refundAmount != null) '${detail.refundAmount!.round()} FCFA remboursés',
          ].join(' · ');
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
      backgroundColor: const Color(0xFFF3F5F9),
      body: Column(
        children: [
          Container(
            color: OvanieColors.navy,
            padding: EdgeInsets.fromLTRB(4, top + 4, 12, 12),
            child: Row(
              children: [
                IconButton(
                  onPressed: () => Navigator.of(context).maybePop(),
                  icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        detail?.summary.subject.isNotEmpty == true ? detail!.summary.subject : 'Demande support',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
                      ),
                      if (detail != null && detail.summary.reference.isNotEmpty)
                        Text(detail.summary.reference, style: const TextStyle(color: Color(0xFFBFD0EA), fontSize: 12.5)),
                    ],
                  ),
                ),
                if (detail != null) _StatusChip(status: detail.summary.status),
              ],
            ),
          ),
          if (context0.isNotEmpty)
            Container(
              width: double.infinity,
              color: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: Text(context0, style: const TextStyle(color: OvanieColors.muted, fontSize: 13)),
            ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
                : _error != null
                    ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
                    : detail == null
                        ? const SizedBox.shrink()
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(12, 14, 12, 12),
                              children: [
                                if (detail.messages.isEmpty)
                                  const Padding(
                                    padding: EdgeInsets.only(top: 60),
                                    child: Text('Aucun échange pour le moment.', textAlign: TextAlign.center, style: TextStyle(color: OvanieColors.muted, fontSize: 14)),
                                  )
                                else
                                  ...detail.messages.map((message) => _MessageBubble(message: message, dateLabel: _date(message.createdAt), onOpenAttachment: _openAttachment)),
                              ],
                            ),
                          ),
          ),
          if (detail != null && closed)
            SafeArea(
              top: false,
              child: Container(
                width: double.infinity,
                color: Colors.white,
                padding: const EdgeInsets.all(14),
                child: const Text('Cette demande est clôturée.', textAlign: TextAlign.center, style: TextStyle(color: OvanieColors.muted, fontSize: 14)),
              ),
            ),
          if (detail != null && !closed)
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(6, 8, 8, 8),
                decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: Color(0xFFE3E8F0)))),
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
                  Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    IconButton(onPressed: _sending ? null : _pick, tooltip: 'Joindre un fichier', icon: const Icon(Icons.attach_file_rounded, color: OvanieColors.muted)),
                    Expanded(
                      child: TextField(
                        controller: _message,
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
                          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(22), borderSide: BorderSide.none),
                          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(22), borderSide: BorderSide.none),
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    IconButton.filled(
                      onPressed: _sending ? null : _send,
                      style: IconButton.styleFrom(backgroundColor: OvanieColors.orange, fixedSize: const Size(46, 46)),
                      icon: _sending
                          ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
                    ),
                  ]),
                ]),
              ),
            ),
        ],
      ),
    ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .14), borderRadius: BorderRadius.circular(20)),
        child: Text(supportStatusLabel(status), style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
      );
}

String supportStatusLabel(String status) => switch (status.toLowerCase()) {
      'open' || 'new' => 'Nouveau',
      'in_progress' || 'pending' || 'waiting_customer' => 'En cours',
      'resolved' || 'closed' => 'Terminé',
      'cancelled' || 'rejected' => 'Fermé',
      _ => status,
    };

String supportCategoryLabel(String category) => switch (category.toLowerCase()) {
      'general' || '' => 'Question générale',
      'orders' || 'order' => 'Commande',
      'delivery' => 'Livraison',
      'payments' || 'payment' => 'Paiement',
      'returns' || 'return' || 'refund' => 'Retour et remboursement',
      'account' => 'Compte',
      'technical' => 'Problème technique',
      _ => category,
    };

class _MessageBubble extends StatelessWidget {
  final SupportTicketMessageItem message;
  final String dateLabel;
  final Future<void> Function(SupportAttachment) onOpenAttachment;
  const _MessageBubble({required this.message, required this.dateLabel, required this.onOpenAttachment});

  @override
  Widget build(BuildContext context) {
    final mine = message.authorType == 'client' || message.authorType == 'customer';
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .78),
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.fromLTRB(13, 9, 13, 7),
        decoration: BoxDecoration(
          color: mine ? OvanieColors.navy : Colors.white,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(mine ? 16 : 4),
            bottomRight: Radius.circular(mine ? 4 : 16),
          ),
          border: mine ? null : Border.all(color: const Color(0xFFE3E8F0)),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (!mine)
            const Padding(
              padding: EdgeInsets.only(bottom: 3),
              child: Text('Support OVANIE', style: TextStyle(color: OvanieColors.orange, fontSize: 12, fontWeight: FontWeight.w700)),
            ),
          if (message.body.isNotEmpty)
            Text(message.body, style: TextStyle(color: mine ? Colors.white : OvanieColors.navy, fontSize: 14.5, height: 1.4)),
          for (final attachment in message.attachments)
            InkWell(
              onTap: () => onOpenAttachment(attachment),
              child: Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.attach_file_rounded, size: 16, color: mine ? Colors.white70 : OvanieColors.blue),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      attachment.name.isEmpty ? 'Pièce jointe' : attachment.name,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: mine ? Colors.white : OvanieColors.blue, fontSize: 13.5, decoration: TextDecoration.underline),
                    ),
                  ),
                ]),
              ),
            ),
          if (dateLabel.isNotEmpty)
            Align(
              alignment: Alignment.bottomRight,
              child: Padding(
                padding: const EdgeInsets.only(top: 3),
                child: Text(dateLabel, style: TextStyle(fontSize: 11, color: mine ? const Color(0xFFBFD0EA) : OvanieColors.muted)),
              ),
            ),
        ]),
      ),
    );
  }
}
