import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/support_repository.dart';

class NewSupportTicketScreen extends StatefulWidget {
  const NewSupportTicketScreen({super.key, this.initialCategory, this.contextType, this.contextId, this.initialSubject});
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
  late String _category = widget.initialCategory ?? 'general';
  List<String> _attachments = const [];
  bool _busy = false;

  @override
  void dispose() { _subject.dispose(); _description.dispose(); super.dispose(); }

  Future<void> _pick() async {
    final result = await FilePicker.pickFiles(
      allowMultiple: true,
      withData: false,
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'txt'],
    );
    if (result == null || !mounted) return;
    final paths = result.files.map((file) => file.path).whereType<String>().where((path) => path.isNotEmpty).toList();
    final merged = <String>{..._attachments, ...paths}.take(4).toList(growable: false);
    setState(() => _attachments = merged);
  }

  Future<void> _submit() async {
    if (_busy) return;
    if (_subject.text.trim().length < 4 || _description.text.trim().length < 10) {
      _message('Renseignez un objet et une description suffisamment détaillée.');
      return;
    }
    setState(() => _busy = true);
    try {
      await const SupportRepository().createTicket(
        category: _category,
        subject: _subject.text,
        description: _description.text,
        contexts: widget.contextType != null && widget.contextId != null
            ? [{'type': widget.contextType!, 'id': widget.contextId!}]
            : const [],
        attachmentPaths: _attachments,
      );
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  String _name(String path) => path.replaceAll('\\', '/').split('/').last;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Nouveau ticket support')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text('Votre demande sera enregistrée dans le même Support Center OVANIE que le Web.', style: TextStyle(color: OvanieColors.muted, height: 1.4)),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _category,
              decoration: const InputDecoration(labelText: 'Catégorie'),
              items: const [
                DropdownMenuItem(value: 'general', child: Text('Général')),
                DropdownMenuItem(value: 'orders', child: Text('Commande')),
                DropdownMenuItem(value: 'payments', child: Text('Paiement')),
                DropdownMenuItem(value: 'delivery', child: Text('Livraison')),
                DropdownMenuItem(value: 'returns', child: Text('Retour / remboursement')),
                DropdownMenuItem(value: 'account', child: Text('Compte')),
                DropdownMenuItem(value: 'technical', child: Text('Problème technique')),
              ],
              onChanged: _busy ? null : (value) => value == null ? null : setState(() => _category = value),
            ),
            const SizedBox(height: 12),
            TextField(controller: _subject, decoration: const InputDecoration(labelText: 'Objet')),
            const SizedBox(height: 12),
            TextField(controller: _description, minLines: 5, maxLines: 10, decoration: const InputDecoration(labelText: 'Description détaillée')),
            const SizedBox(height: 16),
            OutlinedButton.icon(onPressed: _busy ? null : _pick, icon: const Icon(Icons.attach_file_rounded), label: Text(_attachments.isEmpty ? 'Ajouter des pièces jointes' : 'Ajouter / modifier (${_attachments.length}/4)')),
            if (_attachments.isNotEmpty) ...[
              const SizedBox(height: 8),
              ..._attachments.asMap().entries.map((entry) => ListTile(
                    dense: true,
                    leading: const Icon(Icons.insert_drive_file_outlined, color: OvanieColors.blue),
                    title: Text(_name(entry.value), maxLines: 1, overflow: TextOverflow.ellipsis),
                    trailing: IconButton(onPressed: _busy ? null : () => setState(() => _attachments = [..._attachments]..removeAt(entry.key)), icon: const Icon(Icons.close_rounded)),
                  )),
            ],
            const SizedBox(height: 10),
            const Text('Formats autorisés : JPG, PNG, WEBP, PDF, DOC, DOCX, TXT. Maximum 4 fichiers de 5 Mo chacun. La validation finale est faite par Laravel.', style: TextStyle(color: OvanieColors.muted, fontSize: 10.5, height: 1.35)),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _busy ? null : _submit,
              style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange, minimumSize: const Size.fromHeight(50)),
              icon: _busy ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_outlined),
              label: const Text('Créer le ticket', style: TextStyle(fontWeight: FontWeight.w900)),
            ),
          ],
        ),
      );
}
