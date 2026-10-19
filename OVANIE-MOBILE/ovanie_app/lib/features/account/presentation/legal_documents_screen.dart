import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);

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
        _error = null;
      });
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _open(LegalDocumentItem item) async {
    if (!item.available || (item.url ?? '').trim().isEmpty) {
      showOvanieToast(context, item.message.isEmpty ? 'Document non publié actuellement.' : item.message, error: true);
      return;
    }
    final opened = await ExternalUrlLauncher.open(item.url!);
    if (!opened && mounted) showOvanieToast(context, 'Impossible d’ouvrir le document.', error: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_rounded, color: _navy, size: 26),
        ),
        title: const Text(
          'CGU & politiques',
          style: TextStyle(color: _navy, fontSize: 21, fontWeight: FontWeight.w800),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
          : _error != null && _items.isEmpty
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.cloud_off_rounded, color: _muted, size: 52),
                    const SizedBox(height: 14),
                    Text(
                      _error!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: _muted, height: 1.45),
                    ),
                    const SizedBox(height: 18),
                    PaymentButton(label: 'Réessayer', onPressed: _load),
                  ],
                ),
              ),
            )
          : RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(14, 6, 14, 24),
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
                    decoration: BoxDecoration(color: const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(10)),
                    child: const Text(
                      'Retrouvez ici les documents officiels publiés par OVANIE.',
                      style: TextStyle(color: Color(0xFF4A586E), fontSize: 13.5),
                    ),
                  ),
                  const SizedBox(height: 14),
                  for (final item in _items) ...[
                    _DocumentTile(item: item, onTap: () => _open(item)),
                    const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
    );
  }
}

class _DocumentTile extends StatelessWidget {
  const _DocumentTile({required this.item, required this.onTap});

  final LegalDocumentItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final published = item.available && (item.url ?? '').trim().isNotEmpty;
    return Material(
      color: published ? Colors.white : const Color(0xFFF2F4F7),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: _line),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
          child: Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(color: const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(10)),
                child: Icon(Icons.description_outlined, color: published ? _navy : const Color(0xFF8A96A8), size: 26),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      style: TextStyle(
                        color: published ? _navy : const Color(0xFF5E6B80),
                        fontSize: 15.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: published ? const Color(0xFF16A34A) : const Color(0xFF9AA5B5),
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          published ? 'Document publié' : 'Non publié',
                          style: TextStyle(
                            color: published ? const Color(0xFF15803D) : const Color(0xFF8A96A8),
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Icon(
                published ? Icons.chevron_right_rounded : Icons.lock_outline_rounded,
                color: const Color(0xFF8A96A8),
                size: published ? 24 : 20,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
