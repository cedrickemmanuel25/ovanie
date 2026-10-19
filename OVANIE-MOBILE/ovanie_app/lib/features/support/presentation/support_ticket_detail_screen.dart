import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../../core/utils/formatters.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../payments/presentation/payment_ui.dart';
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
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final detail = await _repository.ticket(widget.ticketId);
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
      showOvanieToast(context, ApiClient.friendlyError(error), error: true);
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
        if (!opened && mounted) {
          throw const OvanieApiException('Cette pièce jointe ne peut pas être ouverte sur ce téléphone.');
        }
      }
    } catch (error) {
      if (!mounted) return;
      showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    }
  }

  String _date(DateTime? value) {
    if (value == null) return '';
    String two(int n) => n.toString().padLeft(2, '0');
    final d = value.toLocal();
    return '${two(d.day)}/${two(d.month)} · ${two(d.hour)}:${two(d.minute)}';
  }

  String _name(String path) => path.replaceAll('\\', '/').split('/').last;

  String _dayLabel(DateTime value) {
    const months = [
      'janvier',
      'février',
      'mars',
      'avril',
      'mai',
      'juin',
      'juillet',
      'août',
      'septembre',
      'octobre',
      'novembre',
      'décembre',
    ];
    final d = value.toLocal();
    return '${d.day} ${months[d.month - 1]} ${d.year}';
  }

  List<Widget> _thread(SupportTicketDetail detail) {
    final items = <Widget>[];
    String? lastDay;
    for (final message in detail.messages) {
      final at = message.createdAt;
      if (at != null) {
        final day = _dayLabel(at);
        if (day != lastDay) {
          lastDay = day;
          items.add(_DayChip(label: day));
        }
      }
      items.add(
        _MessageBubble(message: message, dateLabel: _date(message.createdAt), onOpenAttachment: _openAttachment),
      );
    }
    return items;
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    final closed = detail != null && ['closed', 'cancelled'].contains(detail.summary.status);
    return Scaffold(
      backgroundColor: const Color(0xFFF6F8FB),
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        titleSpacing: 0,
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_rounded, color: _navy, size: 26),
        ),
        title: const Text(
          'Demande support',
          style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
                : detail == null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(28),
                      child: Text(
                        _error ?? 'Demande introuvable.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: _muted),
                      ),
                    ),
                  )
                : RefreshIndicator(
                    color: OvanieColors.orange,
                    onRefresh: _load,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                      children: [
                        _TicketHeader(detail: detail),
                        if (detail.messages.isEmpty)
                          const Padding(
                            padding: EdgeInsets.only(top: 70),
                            child: Column(
                              children: [
                                CircleAvatar(
                                  radius: 54,
                                  backgroundColor: Color(0xFFE9ECF1),
                                  child: Icon(Icons.forum_outlined, color: Color(0xFF5E6B80), size: 44),
                                ),
                                SizedBox(height: 18),
                                Text(
                                  'Aucun échange pour le moment.',
                                  style: TextStyle(color: _navy, fontSize: 17, fontWeight: FontWeight.w800),
                                ),
                              ],
                            ),
                          )
                        else
                          ..._thread(detail),
                      ],
                    ),
                  ),
          ),
          if (detail != null && closed)
            SafeArea(
              top: false,
              child: Container(
                margin: const EdgeInsets.fromLTRB(14, 6, 14, 10),
                padding: const EdgeInsets.symmetric(vertical: 14),
                decoration: BoxDecoration(color: const Color(0xFFE9ECF1), borderRadius: BorderRadius.circular(14)),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.lock_outline_rounded, color: Color(0xFF4A586E), size: 20),
                    SizedBox(width: 10),
                    Text('Cette demande est clôturée.', style: TextStyle(color: Color(0xFF4A586E), fontSize: 14.5)),
                  ],
                ),
              ),
            ),
          if (detail != null && !closed)
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(6, 8, 10, 8),
                color: Colors.white,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (_attachments.isNotEmpty)
                      SizedBox(
                        height: 42,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          padding: const EdgeInsets.symmetric(horizontal: 6),
                          itemCount: _attachments.length,
                          separatorBuilder: (_, _) => const SizedBox(width: 6),
                          itemBuilder: (context, index) => InputChip(
                            label: ConstrainedBox(
                              constraints: const BoxConstraints(maxWidth: 130),
                              child: Text(_name(_attachments[index]), overflow: TextOverflow.ellipsis),
                            ),
                            onDeleted: _sending
                                ? null
                                : () => setState(() => _attachments = [..._attachments]..removeAt(index)),
                          ),
                        ),
                      ),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        IconButton(
                          onPressed: _sending ? null : _pick,
                          tooltip: 'Joindre un fichier',
                          icon: const Icon(Icons.attach_file_rounded, color: _muted),
                        ),
                        Expanded(
                          child: TextField(
                            controller: _message,
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
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(24),
                                borderSide: BorderSide.none,
                              ),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(24),
                                borderSide: BorderSide.none,
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(24),
                                borderSide: BorderSide.none,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        SizedBox.square(
                          dimension: 46,
                          child: IconButton.filled(
                            onPressed: _sending ? null : _send,
                            tooltip: 'Envoyer',
                            style: IconButton.styleFrom(backgroundColor: OvanieColors.orange),
                            icon: _sending
                                ? const SizedBox.square(
                                    dimension: 18,
                                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                  )
                                : const Icon(Icons.send_rounded, color: Colors.white, size: 21),
                          ),
                        ),
                      ],
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

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);

String supportStatusLabel(String status) => switch (status.toLowerCase()) {
  'open' || 'new' => 'Nouveau',
  'in_progress' || 'pending' || 'waiting_customer' => 'En cours',
  'resolved' => 'Terminé',
  'closed' || 'cancelled' || 'rejected' => 'Fermé',
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

class _TicketHeader extends StatelessWidget {
  const _TicketHeader({required this.detail});

  final SupportTicketDetail detail;

  @override
  Widget build(BuildContext context) {
    final summary = detail.summary;
    final status = supportStatusLabel(summary.status);
    final (statusBg, statusFg) = switch (status) {
      'Terminé' => (const Color(0xFFE5F6EB), const Color(0xFF15803D)),
      'Fermé' => (const Color(0xFFE9ECF1), const Color(0xFF4A586E)),
      _ => (const Color(0xFFFFEBD2), const Color(0xFFC2620A)),
    };
    final reference = summary.reference.isEmpty ? 'SUP-${summary.id}' : summary.reference.replaceFirst('#', '');
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE7ECF2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 6,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                reference,
                style: const TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(color: statusBg, borderRadius: BorderRadius.circular(99)),
                child: Text(
                  status,
                  style: TextStyle(color: statusFg, fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          if (summary.subject.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(summary.subject, style: const TextStyle(color: _navy, fontSize: 15.5)),
          ],
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
            decoration: BoxDecoration(color: const Color(0xFFEDF0F4), borderRadius: BorderRadius.circular(99)),
            child: Text(
              supportCategoryLabel(summary.category),
              style: const TextStyle(color: Color(0xFF4A586E), fontSize: 13),
            ),
          ),
          if (detail.orderNumber.isNotEmpty) ...[
            const SizedBox(height: 8),
            InkWell(
              onTap: (detail.orderId ?? 0) > 0
                  ? () => Navigator.of(
                      context,
                    ).push(MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: detail.orderId!)))
                  : null,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Commande ${detail.orderNumber}',
                    style: const TextStyle(
                      color: Color(0xFF1E5BD8),
                      fontSize: 14.5,
                      decoration: TextDecoration.underline,
                      decorationColor: Color(0xFF1E5BD8),
                    ),
                  ),
                  const Icon(Icons.chevron_right_rounded, color: Color(0xFF1E5BD8), size: 20),
                ],
              ),
            ),
          ],
          if (detail.refundAmount != null) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(color: const Color(0xFFE8F6EC), borderRadius: BorderRadius.circular(8)),
              child: Row(
                children: [
                  const Icon(Icons.check_circle_rounded, color: Color(0xFF15803D), size: 20),
                  const SizedBox(width: 8),
                  Text(
                    '${formatFcfa(detail.refundAmount!)} remboursés',
                    style: const TextStyle(color: Color(0xFF15803D), fontSize: 14, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _DayChip extends StatelessWidget {
  const _DayChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      margin: const EdgeInsets.symmetric(vertical: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(color: const Color(0xFFE9ECF1), borderRadius: BorderRadius.circular(99)),
      child: Text(label, style: const TextStyle(color: Color(0xFF5E6B80), fontSize: 12.5)),
    ),
  );
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message, required this.dateLabel, required this.onOpenAttachment});

  final SupportTicketMessageItem message;
  final String dateLabel;
  final Future<void> Function(SupportAttachment) onOpenAttachment;

  @override
  Widget build(BuildContext context) {
    final mine = message.authorType == 'client' || message.authorType == 'customer';
    final maxWidth = MediaQuery.sizeOf(context).width * (mine ? .78 : .7);
    final children = <Widget>[
      if (message.body.isNotEmpty)
        Container(
          constraints: BoxConstraints(maxWidth: maxWidth),
          padding: const EdgeInsets.fromLTRB(14, 9, 14, 7),
          decoration: BoxDecoration(
            color: mine ? OvanieColors.navy : Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: mine ? null : Border.all(color: const Color(0xFFE3E8F0)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(message.body, style: TextStyle(color: mine ? Colors.white : _navy, fontSize: 14.5, height: 1.4)),
              if (dateLabel.isNotEmpty)
                Align(
                  alignment: mine ? Alignment.bottomRight : Alignment.bottomLeft,
                  child: Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      dateLabel,
                      style: TextStyle(fontSize: 11.5, color: mine ? const Color(0xFFBFD0EA) : _muted),
                    ),
                  ),
                ),
            ],
          ),
        ),
      for (final attachment in message.attachments)
        Padding(
          padding: const EdgeInsets.only(top: 6),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: maxWidth),
            child: _AttachmentCard(attachment: attachment, onOpen: () => onOpenAttachment(attachment)),
          ),
        ),
    ];

    if (mine) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Column(crossAxisAlignment: CrossAxisAlignment.end, children: children),
      );
    }
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const CircleAvatar(
            radius: 20,
            backgroundColor: OvanieColors.navy,
            child: Icon(Icons.headset_mic_rounded, color: Colors.white, size: 21),
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Padding(
                  padding: EdgeInsets.only(left: 2, bottom: 4),
                  child: Text(
                    'Support OVANIE',
                    style: TextStyle(color: Color(0xFF4A586E), fontSize: 12.5, fontWeight: FontWeight.w700),
                  ),
                ),
                ...children,
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Pièce jointe : aperçu pour les photos, icône pour les documents.
class _AttachmentCard extends StatefulWidget {
  const _AttachmentCard({required this.attachment, required this.onOpen});

  final SupportAttachment attachment;
  final VoidCallback onOpen;

  @override
  State<_AttachmentCard> createState() => _AttachmentCardState();
}

class _AttachmentCardState extends State<_AttachmentCard> {
  Future<File>? _preview;

  bool get _isImage {
    final a = widget.attachment;
    final name = a.name.toLowerCase();
    return a.mime.startsWith('image/') || RegExp(r'\.(jpe?g|png|webp)$').hasMatch(name);
  }

  @override
  void initState() {
    super.initState();
    if (_isImage && widget.attachment.downloadEndpoint.isNotEmpty) {
      _preview = const SupportRepository().downloadAttachment(widget.attachment);
    }
  }

  String get _size {
    final bytes = widget.attachment.size;
    if (bytes == null || bytes <= 0) return '';
    if (bytes >= 1024 * 1024) return '${(bytes / (1024 * 1024)).toStringAsFixed(1).replaceAll('.', ',')} Mo';
    return '${(bytes / 1024).round()} Ko';
  }

  @override
  Widget build(BuildContext context) {
    final a = widget.attachment;
    final pdf = a.mime.contains('pdf') || a.name.toLowerCase().endsWith('.pdf');
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Color(0xFFE3E8F0)),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: widget.onOpen,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_preview != null)
              FutureBuilder<File>(
                future: _preview,
                builder: (context, snapshot) => SizedBox(
                  height: 120,
                  child: snapshot.hasData
                      ? Image.file(snapshot.data!, fit: BoxFit.cover, cacheWidth: 600)
                      : const ColoredBox(color: Color(0xFFF0F2F5)),
                ),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 6, 8),
              child: Row(
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: pdf ? const Color(0xFFFDE8E8) : const Color(0xFFF0F2F5),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      pdf ? Icons.picture_as_pdf_rounded : Icons.attach_file_rounded,
                      color: pdf ? const Color(0xFFDC2626) : const Color(0xFF4A586E),
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Pièce jointe',
                          style: TextStyle(color: _navy, fontSize: 13, fontWeight: FontWeight.w700),
                        ),
                        Text(
                          [a.name.isEmpty ? 'Fichier' : a.name, if (_size.isNotEmpty) _size].join(' • '),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: _muted, fontSize: 12.5),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.download_rounded, color: Color(0xFF4A586E), size: 22),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
