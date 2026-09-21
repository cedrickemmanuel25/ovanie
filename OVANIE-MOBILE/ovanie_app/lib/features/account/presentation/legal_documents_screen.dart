import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

class LegalDocumentsScreen extends StatefulWidget {
  const LegalDocumentsScreen({super.key});
  @override
  State<LegalDocumentsScreen> createState() => _LegalDocumentsScreenState();
}

class _LegalDocumentsScreenState extends State<LegalDocumentsScreen> {
  final _repository = const ClientAccountRepository();
  List<LegalDocumentItem> _items = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final items = await _repository.legalDocuments();
      if (!mounted) return;
      setState(() {
        _items = items;
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

  Future<void> _open(LegalDocumentItem item) async {
    if (!item.available || (item.url ?? '').trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(item.message.isEmpty ? 'Document non publié actuellement.' : item.message)));
      return;
    }
    final opened = await ExternalUrlLauncher.open(item.url!);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Impossible d’ouvrir le document.')));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('CGU & politiques')),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      const Text(
                        'L’application n’invente aucun texte juridique : seuls les documents réellement publiés par OVANIE sont ouvrables.',
                        style: TextStyle(color: OvanieColors.muted, height: 1.45),
                      ),
                      const SizedBox(height: 14),
                      ..._items.map((item) => Card(
                            margin: const EdgeInsets.only(bottom: 10),
                            child: ListTile(
                              onTap: () => _open(item),
                              leading: Icon(item.available ? Icons.description_outlined : Icons.pending_outlined, color: item.available ? OvanieColors.blue : OvanieColors.muted),
                              title: Text(item.title, style: const TextStyle(fontWeight: FontWeight.w800)),
                              subtitle: Text(item.available ? 'Document publié' : (item.message.isEmpty ? 'Non publié' : item.message)),
                              trailing: Icon(item.available ? Icons.open_in_new_rounded : Icons.lock_outline_rounded),
                            ),
                          )),
                    ],
                  ),
      );
}
