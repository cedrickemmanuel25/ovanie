import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../addresses/presentation/addresses_screen.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/login_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../returns/presentation/returns_screen.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/support_repository.dart';
import '../domain/support_models.dart';
import 'client_ai_chat_screen.dart';
import 'new_support_ticket_screen.dart';
import 'support_ticket_detail_screen.dart';
import 'support_whatsapp_screen.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);
const _pageBackground = Color(0xFFF6F8FB);

class SupportCenterScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;
  final String? initialCategory;
  final String? contextType;
  final int? contextId;
  final String? initialSubject;

  const SupportCenterScreen({
    super.key,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenCart,
    this.onOpenFavorites,
    this.initialCategory,
    this.contextType,
    this.contextId,
    this.initialSubject,
  });

  @override
  State<SupportCenterScreen> createState() => _SupportCenterScreenState();
}

class _SupportCenterScreenState extends State<SupportCenterScreen> {
  final _repository = const SupportRepository();
  SupportCenterData? _data;
  bool _loading = true;
  String? _error;
  int? _openFaq = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _repository.center();
      if (!mounted) return;
      setState(() {
        _data = data;
        _error = null;
      });
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _notice(String message) {
    if (mounted) showOvanieToast(context, message, error: true);
  }

  Future<void> _newTicket() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => NewSupportTicketScreen(
          initialCategory: widget.initialCategory,
          contextType: widget.contextType,
          contextId: widget.contextId,
          initialSubject: widget.initialSubject,
        ),
      ),
    );
    if (created == true) await _load();
  }

  Future<void> _newConversation() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const ClientAiChatScreen()));
    await _load();
  }

  Future<void> _openPhone() async {
    final phone = _data?.phone.trim() ?? '';
    if (phone.isEmpty) {
      _notice('Le numéro du support est momentanément indisponible.');
      return;
    }
    final ok = await ExternalUrlLauncher.open('tel:$phone');
    if (!ok) _notice('Impossible d’ouvrir l’application Téléphone.');
  }

  Future<void> _openWhatsApp() async {
    final data = _data;
    if (data == null || !data.whatsappAvailable || data.whatsappUrl.trim().isEmpty) {
      _notice('WhatsApp Support est momentanément indisponible.');
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SupportWhatsappScreen(number: data.whatsappNumber, url: data.whatsappUrl),
      ),
    );
  }

  Future<void> _openEmail() async {
    const email = 'contact@ovanie.com';
    final ok = await ExternalUrlLauncher.open('mailto:$email?subject=Assistance%20client%20OVANIE');
    if (!ok) _notice('Impossible d’ouvrir votre application e-mail.');
  }

  /// Raccourcis vers les espaces du compte : la connexion est demandée si besoin.
  void _openAccountArea(Widget screen) {
    final target = SessionStore.instance.isAuthenticated ? screen : const LoginScreen();
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => target));
  }

  void _openPaymentFaq() {
    final faqs = _data?.faqs ?? const <SupportFaqItem>[];
    final index = faqs.indexWhere((item) => '${item.title} ${item.category}'.toLowerCase().contains('paiement'));
    if (index < 0) {
      _openAllFaqs();
      return;
    }
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 26),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _SheetHandle(),
              const SizedBox(height: 14),
              Text(
                faqs[index].title,
                style: const TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              Text(faqs[index].content, style: const TextStyle(color: _muted, fontSize: 14.5, height: 1.5)),
            ],
          ),
        ),
      ),
    );
  }

  void _openAllFaqs() {
    final items = _data?.faqs ?? const <SupportFaqItem>[];
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (context) => SafeArea(
        top: false,
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * .78,
          child: Column(
            children: [
              const SizedBox(height: 10),
              const _SheetHandle(),
              const Padding(
                padding: EdgeInsets.fromLTRB(20, 14, 20, 10),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Questions fréquentes',
                    style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
                  ),
                ),
              ),
              const Divider(height: 1, color: _line),
              Expanded(
                child: items.isEmpty
                    ? const Center(
                        child: Text(
                          'Aucune question fréquente publiée pour le moment.',
                          style: TextStyle(color: _muted),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(12, 6, 12, 24),
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const Divider(height: 1, color: _line),
                        itemBuilder: (context, index) => _FaqTile(item: items[index]),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _openAllTickets() {
    final items = _data?.tickets ?? const <SupportTicketSummary>[];
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (sheetContext) => SafeArea(
        top: false,
        child: SizedBox(
          height: MediaQuery.sizeOf(sheetContext).height * .78,
          child: Column(
            children: [
              const SizedBox(height: 10),
              const _SheetHandle(),
              const Padding(
                padding: EdgeInsets.fromLTRB(20, 14, 20, 10),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Mes demandes',
                    style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
                  ),
                ),
              ),
              const Divider(height: 1, color: _line),
              Expanded(
                child: ListView.separated(
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1, color: _line),
                  itemBuilder: (context, index) => _TicketRow(
                    ticket: items[index],
                    onTap: () {
                      Navigator.of(sheetContext).pop();
                      _openTicket(items[index]);
                    },
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openTicket(SupportTicketSummary ticket) async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => SupportTicketDetailScreen(ticketId: ticket.id)));
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    final faqs = _data?.faqs ?? const <SupportFaqItem>[];
    final tickets = _data?.tickets ?? const <SupportTicketSummary>[];
    final phone = (_data?.phone ?? '').trim();

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: _pageBackground,
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
        body: Stack(
          children: [
            RefreshIndicator(
              onRefresh: _load,
              color: OvanieColors.orange,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: EdgeInsets.zero,
                children: [
                  Stack(
                    children: [
                      Container(
                        height: top + 96,
                        decoration: const BoxDecoration(
                          color: OvanieColors.navy,
                          borderRadius: BorderRadius.vertical(bottom: Radius.circular(24)),
                        ),
                      ),
                      Padding(
                        padding: EdgeInsets.fromLTRB(4, top + 6, 16, 0),
                        child: Row(
                          children: [
                            IconButton(
                              onPressed: () => Navigator.maybePop(context),
                              tooltip: 'Retour',
                              icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 22),
                            ),
                            const SizedBox(width: 4),
                            const Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Aide et support',
                                  style: TextStyle(color: Colors.white, fontSize: 21, fontWeight: FontWeight.w800),
                                ),
                                Text(
                                  'Assistance client OVANIE',
                                  style: TextStyle(color: Color(0xFFC9D3E6), fontSize: 13),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      Padding(
                        padding: EdgeInsets.fromLTRB(12, top + 72, 12, 0),
                        child: _AssistanceCard(onChat: _newConversation, onWrite: _newTicket),
                      ),
                    ],
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 10, 12, 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (_loading)
                          const Padding(
                            padding: EdgeInsets.all(24),
                            child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                          )
                        else if (_error != null && _data == null)
                          _ErrorBanner(message: _error!, onRetry: _load),
                        _ShortcutGrid(
                          onOrders: () => _openAccountArea(const OrdersScreen()),
                          onReturns: () => _openAccountArea(const ReturnsScreen()),
                          onPayment: _openPaymentFaq,
                          onAddresses: () => _openAccountArea(const AddressesScreen()),
                        ),
                        _SectionHeader(title: 'Questions fréquentes', onSeeAll: faqs.isEmpty ? null : _openAllFaqs),
                        _Card(
                          child: faqs.isEmpty
                              ? const _EmptyText('Aucune question fréquente publiée pour le moment.')
                              : Column(
                                  children: [
                                    for (var i = 0; i < faqs.length && i < 4; i++) ...[
                                      if (i > 0) const Divider(height: 1, color: _line, indent: 12, endIndent: 12),
                                      _FaqTile(
                                        item: faqs[i],
                                        expanded: _openFaq == i,
                                        onToggle: () => setState(() => _openFaq = _openFaq == i ? null : i),
                                      ),
                                    ],
                                  ],
                                ),
                        ),
                        const _SectionHeader(title: 'Canaux de contact'),
                        _Card(
                          child: Column(
                            children: [
                              _ContactRow(
                                color: const Color(0xFF1FA855),
                                icon: Icons.chat_rounded,
                                title: 'WhatsApp',
                                subtitle: 'Échangez rapidement avec notre équipe',
                                onTap: _openWhatsApp,
                              ),
                              const Divider(height: 1, color: _line),
                              _ContactRow(
                                color: const Color(0xFF1E6FE8),
                                icon: Icons.call_rounded,
                                title: 'Appel téléphonique',
                                subtitle: 'Appelez le support OVANIE',
                                detail: phone.isEmpty ? null : formatCiPhoneDisplay(phone, includeCountryCode: false),
                                onTap: _openPhone,
                              ),
                              const Divider(height: 1, color: _line),
                              _ContactRow(
                                color: OvanieColors.orange,
                                icon: Icons.mail_outline_rounded,
                                title: 'E-mail',
                                subtitle: 'Envoyez votre demande par e-mail',
                                onTap: _openEmail,
                              ),
                            ],
                          ),
                        ),
                        _SectionHeader(
                          title: 'Mes demandes récentes',
                          onSeeAll: tickets.length > 3 ? _openAllTickets : null,
                        ),
                        _Card(
                          child: tickets.isEmpty
                              ? Padding(
                                  padding: const EdgeInsets.fromLTRB(16, 22, 16, 18),
                                  child: Column(
                                    children: [
                                      const Text(
                                        'Aucune demande récente',
                                        style: TextStyle(color: _navy, fontSize: 16, fontWeight: FontWeight.w700),
                                      ),
                                      const SizedBox(height: 4),
                                      const Text(
                                        'Vous pouvez contacter notre équipe à tout moment.',
                                        textAlign: TextAlign.center,
                                        style: TextStyle(color: _muted, fontSize: 13.5),
                                      ),
                                      const SizedBox(height: 14),
                                      SizedBox(
                                        height: 40,
                                        width: 130,
                                        child: FilledButton(
                                          onPressed: _newTicket,
                                          style: FilledButton.styleFrom(
                                            backgroundColor: OvanieColors.orange,
                                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                                          ),
                                          child: const Text(
                                            'Créer',
                                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                )
                              : Column(
                                  children: [
                                    for (var i = 0; i < tickets.length && i < 3; i++) ...[
                                      if (i > 0) const Divider(height: 1, color: _line),
                                      _TicketRow(ticket: tickets[i], onTap: () => _openTicket(tickets[i])),
                                    ],
                                  ],
                                ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              height: top,
              child: const ColoredBox(color: OvanieColors.navy),
            ),
          ],
        ),
      ),
    );
  }
}

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 42,
      height: 4,
      decoration: BoxDecoration(color: const Color(0xFFD5DBE4), borderRadius: BorderRadius.circular(99)),
    ),
  );
}

class _Card extends StatelessWidget {
  const _Card({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: _line),
    ),
    child: child,
  );
}

class _EmptyText extends StatelessWidget {
  const _EmptyText(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 34),
    child: Text(
      text,
      textAlign: TextAlign.center,
      style: const TextStyle(color: _muted, fontSize: 14),
    ),
  );
}

class _AssistanceCard extends StatelessWidget {
  const _AssistanceCard({required this.onChat, required this.onWrite});

  final VoidCallback onChat;
  final VoidCallback onWrite;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: const [BoxShadow(color: Color(0x140A1B3D), blurRadius: 14, offset: Offset(0, 4))],
      ),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Image.asset('assets/images/checkout/support_agent.jpg', width: 104, fit: BoxFit.cover),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Discuter avec le support',
                    style: TextStyle(color: _navy, fontSize: 15.5, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 2),
                  const Text(
                    'Réponse immédiate, relais vers un conseiller',
                    style: TextStyle(color: _muted, fontSize: 12, height: 1.3),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 38,
                    child: FilledButton(
                      onPressed: onChat,
                      style: FilledButton.styleFrom(
                        backgroundColor: OvanieColors.orange,
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                      ),
                      child: const FittedBox(
                        child: Text(
                          'Discuter avec le support',
                          style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  SizedBox(
                    height: 38,
                    child: OutlinedButton(
                      onPressed: onWrite,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _navy,
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        side: const BorderSide(color: _navy, width: 1.2),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                      ),
                      child: const FittedBox(
                        child: Text(
                          'Envoyer une demande écrite',
                          style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ShortcutGrid extends StatelessWidget {
  const _ShortcutGrid({
    required this.onOrders,
    required this.onReturns,
    required this.onPayment,
    required this.onAddresses,
  });

  final VoidCallback onOrders;
  final VoidCallback onReturns;
  final VoidCallback onPayment;
  final VoidCallback onAddresses;

  @override
  Widget build(BuildContext context) {
    Widget tile(IconData icon, String label, VoidCallback onTap) => Expanded(
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: _line),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(
            height: 54,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 6, 0),
              child: Row(
                children: [
                  Icon(icon, color: _navy, size: 23),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(label, maxLines: 2, style: const TextStyle(color: _navy, fontSize: 13, height: 1.2)),
                  ),
                  const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8), size: 20),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    return Column(
      children: [
        Row(
          children: [
            tile(Icons.inventory_2_outlined, 'Suivre ma commande', onOrders),
            const SizedBox(width: 8),
            tile(Icons.sync_rounded, 'Retour & remboursement', onReturns),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            tile(Icons.payment_outlined, 'Paiement sécurisé', onPayment),
            const SizedBox(width: 8),
            tile(Icons.location_on_outlined, 'Adresses de livraison', onAddresses),
          ],
        ),
      ],
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, this.onSeeAll});

  final String title;
  final VoidCallback? onSeeAll;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 18, 0, 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: const TextStyle(color: _navy, fontSize: 17.5, fontWeight: FontWeight.w800),
            ),
          ),
          if (onSeeAll != null)
            InkWell(
              onTap: onSeeAll,
              borderRadius: BorderRadius.circular(8),
              child: const Padding(
                padding: EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                child: Row(
                  children: [
                    Text(
                      'Voir tout',
                      style: TextStyle(color: OvanieColors.orange, fontSize: 13.5, fontWeight: FontWeight.w600),
                    ),
                    Icon(Icons.chevron_right_rounded, color: OvanieColors.orange, size: 20),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

(String, Color, Color) _faqTag(String category) {
  final c = category.toLowerCase();
  const blue = (Color(0xFFE8F1FF), Color(0xFF1E5BB8));
  final (label, colors) = switch (c) {
    _ when c.contains('livr') || c.contains('deliver') => ('Livraison', blue),
    _ when c.contains('retour') || c.contains('return') || c.contains('rembours') => (
      'Retour',
      (const Color(0xFFE5F6EB), const Color(0xFF15803D)),
    ),
    _ when c.contains('paie') || c.contains('pay') => ('Paiement', (const Color(0xFFFFF0E0), const Color(0xFFC2620A))),
    _ when c.contains('compte') || c.contains('account') => (
      'Compte',
      (const Color(0xFFF1EBFF), const Color(0xFF6D3FD0)),
    ),
    _ when c.contains('command') || c.contains('order') => ('Commande', blue),
    _ => ('Général', (const Color(0xFFF0F2F5), const Color(0xFF4A586E))),
  };
  return (label, colors.$1, colors.$2);
}

class _FaqTile extends StatefulWidget {
  const _FaqTile({required this.item, this.expanded, this.onToggle});

  final SupportFaqItem item;
  final bool? expanded;
  final VoidCallback? onToggle;

  @override
  State<_FaqTile> createState() => _FaqTileState();
}

class _FaqTileState extends State<_FaqTile> {
  bool _localExpanded = false;

  bool get _expanded => widget.expanded ?? _localExpanded;

  @override
  Widget build(BuildContext context) {
    final (label, bg, fg) = _faqTag(widget.item.category.isEmpty ? widget.item.title : widget.item.category);
    return InkWell(
      onTap: widget.onToggle ?? () => setState(() => _localExpanded = !_localExpanded),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 12, 10, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 76,
              padding: const EdgeInsets.symmetric(vertical: 4),
              alignment: Alignment.center,
              decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
              child: Text(
                label,
                style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w500),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.item.title,
                    style: const TextStyle(color: _navy, fontSize: 14, fontWeight: FontWeight.w600, height: 1.3),
                  ),
                  if (_expanded) ...[
                    const SizedBox(height: 5),
                    Text(widget.item.content, style: const TextStyle(color: _muted, fontSize: 13, height: 1.45)),
                  ],
                ],
              ),
            ),
            Icon(
              _expanded ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded,
              color: const Color(0xFF4A586E),
            ),
          ],
        ),
      ),
    );
  }
}

class _ContactRow extends StatelessWidget {
  const _ContactRow({
    required this.color,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.detail,
  });

  final Color color;
  final IconData icon;
  final String title;
  final String subtitle;
  final String? detail;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 10, 10),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              child: Icon(icon, color: Colors.white, size: 23),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(color: _navy, fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  Text(subtitle, style: const TextStyle(color: _muted, fontSize: 13)),
                  if (detail != null)
                    Text(
                      detail!,
                      style: const TextStyle(color: Color(0xFF1E6FE8), fontSize: 14, fontWeight: FontWeight.w700),
                    ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8)),
          ],
        ),
      ),
    );
  }
}

class _TicketRow extends StatelessWidget {
  const _TicketRow({required this.ticket, required this.onTap});

  final SupportTicketSummary ticket;
  final VoidCallback onTap;

  (String, Color, Color) get _status {
    final value = ticket.status.toLowerCase();
    if (value.contains('resolv')) return ('Terminé', const Color(0xFFE5F6EB), const Color(0xFF15803D));
    if (value.contains('clos') || value.contains('cancel') || value.contains('reject') || value.contains('refus')) {
      return ('Fermé', const Color(0xFFF0F2F5), const Color(0xFF4A586E));
    }
    return ('En cours', const Color(0xFFFFF0E0), const Color(0xFFC2620A));
  }

  String _dateLabel(DateTime? date) {
    if (date == null) return '';
    final local = date.toLocal();
    final now = DateTime.now();
    if (now.year == local.year && now.month == local.month && now.day == local.day) return 'Aujourd’hui';
    return paymentShortDate(local);
  }

  @override
  Widget build(BuildContext context) {
    final (label, bg, fg) = _status;
    final reference = ticket.reference.isEmpty ? 'SUP-${ticket.id}' : ticket.reference.replaceFirst('#', '');
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 9, 6, 9),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    reference,
                    style: const TextStyle(color: _navy, fontSize: 13.5, fontWeight: FontWeight.w700),
                  ),
                  Text(
                    ticket.subject,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: _muted, fontSize: 12.5),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(_dateLabel(ticket.updatedAt ?? ticket.createdAt), style: const TextStyle(color: _muted, fontSize: 12)),
            const SizedBox(width: 8),
            Container(
              width: 72,
              padding: const EdgeInsets.symmetric(vertical: 4),
              alignment: Alignment.center,
              decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
              child: Text(
                label,
                style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600),
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8)),
          ],
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(12, 8, 6, 8),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFF7C9C9)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(message, style: const TextStyle(color: OvanieColors.danger, fontSize: 13)),
          ),
          TextButton(
            onPressed: onRetry,
            child: const Text(
              'Réessayer',
              style: TextStyle(color: OvanieColors.orange, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}
