import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/support_repository.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _fieldBorder = Color(0xFFD5DCE6);

const _categories = <(String, String, IconData)>[
  ('general', 'Général', Icons.article_outlined),
  ('orders', 'Commande', Icons.inventory_2_outlined),
  ('payments', 'Paiement', Icons.payment_outlined),
  ('delivery', 'Livraison', Icons.local_shipping_outlined),
  ('returns', 'Retour / remboursement', Icons.sync_rounded),
  ('account', 'Compte', Icons.person_outline_rounded),
  ('technical', 'Problème technique', Icons.settings_outlined),
];

class NewSupportTicketScreen extends StatefulWidget {
  const NewSupportTicketScreen({
    super.key,
    this.initialCategory,
    this.contextType,
    this.contextId,
    this.initialSubject,
  });

  final String? initialCategory;
  final String? contextType;
  final int? contextId;
  final String? initialSubject;

  @override
  State<NewSupportTicketScreen> createState() => _NewSupportTicketScreenState();
}

class _NewSupportTicketScreenState extends State<NewSupportTicketScreen> {
  late final _subject = TextEditingController(text: widget.initialSubject ?? '');
  final _description = TextEditingController();
  late String _category = _categories.any((c) => c.$1 == widget.initialCategory) ? widget.initialCategory! : 'general';
  List<String> _attachments = const [];
  bool _busy = false;
  String? _error;
  bool _subjectInvalid = false;
  bool _descriptionInvalid = false;

  @override
  void initState() {
    super.initState();
    _description.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _subject.dispose();
    _description.dispose();
    super.dispose();
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

  Future<void> _submit() async {
    if (_busy) return;
    FocusScope.of(context).unfocus();
    final subjectInvalid = _subject.text.trim().length < 4;
    final descriptionInvalid = _description.text.trim().length < 10;
    if (subjectInvalid || descriptionInvalid) {
      setState(() {
        _subjectInvalid = subjectInvalid;
        _descriptionInvalid = descriptionInvalid;
        _error = 'Renseignez un objet et une description suffisamment détaillée.';
      });
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await const SupportRepository().createTicket(
        category: _category,
        subject: _subject.text,
        description: _description.text,
        contexts: widget.contextType != null && widget.contextId != null
            ? [
                {'type': widget.contextType!, 'id': widget.contextId!},
              ]
            : const [],
        attachmentPaths: _attachments,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: const Text(
          'Nouveau ticket support',
          style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
          child: SizedBox(
            height: 50,
            child: FilledButton(
              onPressed: _busy ? () {} : _submit,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: _busy
                  ? const SizedBox.square(
                      dimension: 22,
                      child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                    )
                  : const Text('Créer le ticket', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            ),
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(14, 4, 14, 20),
        children: [
          _Banner(
            background: const Color(0xFFE8F1FF),
            iconColor: const Color(0xFF1E4FA8),
            textColor: const Color(0xFF1E3F87),
            icon: Icons.info_outline_rounded,
            text: 'Votre demande sera transmise au service client OVANIE et suivie depuis votre compte.',
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            _Banner(
              background: const Color(0xFFFDECEC),
              iconColor: OvanieColors.danger,
              textColor: const Color(0xFFA11B1B),
              icon: Icons.error_outline_rounded,
              text: _error!,
            ),
          ],
          const _Title('Catégorie'),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final (key, label, icon) in _categories)
                _CategoryChip(
                  label: label,
                  icon: icon,
                  selected: _category == key,
                  onTap: _busy ? null : () => setState(() => _category = key),
                ),
            ],
          ),
          const _Title('Objet'),
          _Input(
            controller: _subject,
            hint: 'Ex. Sac de ciment abîmé – commande',
            invalid: _subjectInvalid,
            onChanged: () {
              if (_subjectInvalid) setState(() => _subjectInvalid = false);
            },
          ),
          const _Title('Description détaillée'),
          _Input(
            controller: _description,
            hint: 'Décrivez votre demande en détail (ex. : contexte, problème rencontré, références, etc.).',
            invalid: _descriptionInvalid,
            minLines: 5,
            maxLength: 1000,
            counter: '${_description.text.length}/1000',
            onChanged: () {
              if (_descriptionInvalid) setState(() => _descriptionInvalid = false);
            },
          ),
          const _Title('Ajouter des pièces jointes'),
          InkWell(
            onTap: _busy ? null : _pick,
            borderRadius: BorderRadius.circular(12),
            child: CustomPaint(
              foregroundPainter: _DashedBorderPainter(),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 18),
                decoration: BoxDecoration(color: const Color(0xFFF6F8FB), borderRadius: BorderRadius.circular(12)),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.attach_file_rounded, color: _navy, size: 24),
                        const SizedBox(width: 8),
                        Text(
                          'Ajouter / modifier (${_attachments.length}/4)',
                          style: const TextStyle(color: OvanieColors.orange, fontSize: 15, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    const Text('Touchez pour sélectionner des fichiers', style: TextStyle(color: _muted, fontSize: 13)),
                  ],
                ),
              ),
            ),
          ),
          if (_attachments.isNotEmpty) ...[
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              childAspectRatio: 1.25,
              children: [
                for (var i = 0; i < _attachments.length; i++)
                  _AttachmentTile(
                    path: _attachments[i],
                    onRemove: _busy ? null : () => setState(() => _attachments = [..._attachments]..removeAt(i)),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 10),
          const Text(
            'Formats autorisés : JPG, PNG, WEBP, PDF, DOC, DOCX, TXT. Maximum 4 fichiers de 5 Mo chacun.',
            style: TextStyle(color: _muted, fontSize: 12.5, height: 1.35),
          ),
        ],
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({
    required this.background,
    required this.iconColor,
    required this.textColor,
    required this.icon,
    required this.text,
  });

  final Color background;
  final Color iconColor;
  final Color textColor;
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          Icon(icon, color: iconColor, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Text(text, style: TextStyle(color: textColor, fontSize: 13.5, height: 1.4)),
          ),
        ],
      ),
    );
  }
}

class _Title extends StatelessWidget {
  const _Title(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 18, bottom: 10),
    child: Text(
      text,
      style: const TextStyle(color: _navy, fontSize: 17, fontWeight: FontWeight.w800),
    ),
  );
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({required this.label, required this.icon, required this.selected, required this.onTap});

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? OvanieColors.orange : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(9),
        side: BorderSide(color: selected ? OvanieColors.orange : const Color(0xFFDDE3EB)),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(9),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 21, color: selected ? Colors.white : _navy),
              const SizedBox(width: 9),
              Text(
                label,
                style: TextStyle(
                  color: selected ? Colors.white : _navy,
                  fontSize: 13.5,
                  fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Input extends StatelessWidget {
  const _Input({
    required this.controller,
    required this.hint,
    required this.invalid,
    required this.onChanged,
    this.minLines = 1,
    this.maxLength,
    this.counter,
  });

  final TextEditingController controller;
  final String hint;
  final bool invalid;
  final VoidCallback onChanged;
  final int minLines;
  final int? maxLength;
  final String? counter;

  @override
  Widget build(BuildContext context) {
    OutlineInputBorder border(Color color, [double width = 1.1]) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: BorderSide(color: color, width: width),
    );
    return Stack(
      children: [
        TextField(
          controller: controller,
          minLines: minLines,
          maxLines: minLines > 1 ? 10 : 1,
          maxLength: maxLength,
          textCapitalization: TextCapitalization.sentences,
          onChanged: (_) => onChanged(),
          style: const TextStyle(color: _navy, fontSize: 15),
          decoration: InputDecoration(
            hintText: hint,
            hintMaxLines: 3,
            hintStyle: const TextStyle(color: Color(0xFF9AA5B5), fontSize: 14.5),
            filled: true,
            fillColor: Colors.white,
            counterText: '',
            contentPadding: EdgeInsets.fromLTRB(14, 14, 14, counter == null ? 14 : 28),
            enabledBorder: border(invalid ? OvanieColors.danger : _fieldBorder),
            focusedBorder: border(invalid ? OvanieColors.danger : _navy, 1.3),
          ),
        ),
        if (counter != null)
          Positioned(
            right: 12,
            bottom: 8,
            child: Text(counter!, style: const TextStyle(color: _muted, fontSize: 12)),
          ),
      ],
    );
  }
}

class _AttachmentTile extends StatelessWidget {
  const _AttachmentTile({required this.path, required this.onRemove});

  final String path;
  final VoidCallback? onRemove;

  String get _name => path.replaceAll('\\', '/').split('/').last;

  String get _extension {
    final dot = _name.lastIndexOf('.');
    return dot < 0 ? '' : _name.substring(dot + 1).toLowerCase();
  }

  String _size() {
    try {
      final bytes = File(path).lengthSync();
      if (bytes >= 1024 * 1024) return '${(bytes / (1024 * 1024)).toStringAsFixed(1).replaceAll('.', ',')} Mo';
      return '${(bytes / 1024).round()} Ko';
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final ext = _extension;
    final image = const {'jpg', 'jpeg', 'png', 'webp'}.contains(ext);
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(12)),
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (image)
            Image.file(File(path), fit: BoxFit.cover, cacheWidth: 400)
          else
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 14, 8, 26),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    ext == 'pdf' ? Icons.picture_as_pdf_rounded : Icons.description_rounded,
                    color: ext == 'pdf' ? const Color(0xFFDC2626) : const Color(0xFF1E5BB8),
                    size: 40,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: _navy, fontSize: 12.5),
                  ),
                  Text(_size(), style: const TextStyle(color: _muted, fontSize: 11.5)),
                ],
              ),
            ),
          Positioned(
            left: 8,
            bottom: 8,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: const Color(0xCC4A586E), borderRadius: BorderRadius.circular(6)),
              child: Text(
                ext.toUpperCase(),
                style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w600),
              ),
            ),
          ),
          if (onRemove != null)
            Positioned(
              right: 6,
              top: 6,
              child: Material(
                color: Colors.white,
                shape: const CircleBorder(),
                child: InkWell(
                  onTap: onRemove,
                  customBorder: const CircleBorder(),
                  child: const Padding(
                    padding: EdgeInsets.all(5),
                    child: Icon(Icons.close_rounded, color: _navy, size: 18),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _DashedBorderPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFB9C3D0)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    final path = Path()..addRRect(RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(12)));
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, distance + 6), paint);
        distance += 11;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
