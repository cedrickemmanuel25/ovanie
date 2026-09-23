import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/external_url_launcher.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../cart/domain/cart_store.dart';
import '../data/support_repository.dart';
import '../domain/support_models.dart';
import 'new_support_ticket_screen.dart';
import 'support_conversation_screen.dart';
import 'support_ticket_detail_screen.dart';
import 'support_whatsapp_screen.dart';

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
  static const _navy = Color(0xFF071B67);
  static const _textBlue = Color(0xFF24366F);
  static const _line = Color(0xFFE5EAF3);
  static const _softBlue = Color(0xFFF7FAFF);

  final _repository = const SupportRepository();
  SupportCenterData? _data;
  bool _loading = true;
  String? _error;

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

  Future<void> _newTicket() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(builder: (_) => NewSupportTicketScreen(
        initialCategory: widget.initialCategory,
        contextType: widget.contextType,
        contextId: widget.contextId,
        initialSubject: widget.initialSubject,
      )),
    );
    if (created == true) await _load();
  }

  Future<void> _newConversation() async {
    final subject = TextEditingController(text: 'Assistance client OVANIE');
    final message = TextEditingController();
    final started = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          18,
          20,
          MediaQuery.viewInsetsOf(context).bottom + 20,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Center(
                child: SizedBox(
                  width: 38,
                  child: Divider(thickness: 4, color: Color(0xFFDCE1EA)),
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Chat support',
                style: TextStyle(
                  color: _navy,
                  fontSize: 21,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              const Text(
                'Décrivez votre besoin et démarrez une conversation avec notre équipe.',
                style: TextStyle(color: _textBlue, fontSize: 13.5, height: 1.35),
              ),
              const SizedBox(height: 18),
              TextField(
                controller: subject,
                decoration: const InputDecoration(labelText: 'Objet'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: message,
                minLines: 4,
                maxLines: 7,
                decoration: const InputDecoration(labelText: 'Votre message'),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: FilledButton(
                  onPressed: () async {
                    if (message.text.trim().isEmpty) return;
                    try {
                      final token = await _repository.startConversation(
                        subject: subject.text,
                        message: message.text,
                      );
                      if (context.mounted) Navigator.pop(context, token);
                    } catch (error) {
                      if (!context.mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text(ApiClient.friendlyError(error))),
                      );
                    }
                  },
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Démarrer la conversation',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    subject.dispose();
    message.dispose();
    if (started != null && started.isNotEmpty && mounted) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => SupportConversationScreen(token: started),
        ),
      );
      await _load();
    }
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
        builder: (_) => SupportWhatsappScreen(
          number: data.whatsappNumber,
          url: data.whatsappUrl,
        ),
      ),
    );
  }

  Future<void> _openEmail() async {
    const email = 'contact@ovanie.com';
    final ok = await ExternalUrlLauncher.open(
      'mailto:$email?subject=Assistance%20client%20OVANIE',
    );
    if (!ok) _notice('Impossible d’ouvrir votre application e-mail.');
  }

  void _notice(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  void _openAllFaqs() {
    final items = _data?.faqs ?? const <SupportFaqItem>[];
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        final height = MediaQuery.sizeOf(context).height * .78;
        return SafeArea(
          top: false,
          child: SizedBox(
            height: height,
            child: Column(
              children: [
                const SizedBox(height: 8),
                const SizedBox(
                  width: 42,
                  child: Divider(thickness: 4, color: Color(0xFFDCE1EA)),
                ),
                const Padding(
                  padding: EdgeInsets.fromLTRB(20, 12, 20, 10),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Questions fréquentes',
                      style: TextStyle(
                        color: _navy,
                        fontSize: 21,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
                const Divider(height: 1),
                Expanded(
                  child: items.isEmpty
                      ? const Center(
                          child: Padding(
                            padding: EdgeInsets.all(30),
                            child: Text(
                              'Aucune question fréquente publiée pour le moment.',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: _textBlue),
                            ),
                          ),
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
                          itemCount: items.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final item = items[index];
                            return ExpansionTile(
                              tilePadding: const EdgeInsets.symmetric(horizontal: 4),
                              childrenPadding: const EdgeInsets.fromLTRB(4, 0, 4, 16),
                              iconColor: _navy,
                              collapsedIconColor: _navy,
                              title: Text(
                                item.title,
                                style: const TextStyle(
                                  color: _navy,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              children: [
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: Text(
                                    item.content,
                                    style: const TextStyle(
                                      color: _textBlue,
                                      fontSize: 13,
                                      height: 1.45,
                                    ),
                                  ),
                                ),
                              ],
                            );
                          },
                        ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _openFaqTopic(String topic) {
    final items = _data?.faqs ?? const <SupportFaqItem>[];
    if (items.isEmpty) {
      _openAllFaqs();
      return;
    }
    final words = topic.toLowerCase().split(' ').where((word) => word.length >= 4);
    SupportFaqItem? match;
    for (final item in items) {
      final haystack = '${item.title} ${item.category}'.toLowerCase();
      if (words.any((word) => haystack.contains(word))) {
        match = item;
        break;
      }
    }
    final selected = match ?? items.first;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 26),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Center(
                child: SizedBox(
                  width: 42,
                  child: Divider(thickness: 4, color: Color(0xFFDCE1EA)),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                selected.title,
                style: const TextStyle(
                  color: _navy,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                selected.content,
                style: const TextStyle(
                  color: _textBlue,
                  fontSize: 14,
                  height: 1.5,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _goToRoot(VoidCallback? action) {
    Navigator.of(context).pop();
    action?.call();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : RefreshIndicator(
                onRefresh: _load,
                color: OvanieColors.orange,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 22),
                  children: [
                    _Header(onBack: () => Navigator.of(context).pop()),
                    const SizedBox(height: 16),
                    _AssistanceHero(onContact: _newTicket),
                    if (_error != null) ...[
                      const SizedBox(height: 10),
                      _ErrorBanner(message: _error!, onRetry: _load),
                    ],
                    const SizedBox(height: 18),
                    const _SectionTitle('Canaux de contact'),
                    const SizedBox(height: 8),
                    _ContactChannels(
                      onWhatsApp: _openWhatsApp,
                      onPhone: _openPhone,
                      onChat: _newConversation,
                      onEmail: _openEmail,
                    ),
                    const SizedBox(height: 17),
                    _SectionTitleRow(onSeeAll: _openAllFaqs),
                    const SizedBox(height: 7),
                    _FaqQuickList(onTap: _openFaqTopic),
                    const SizedBox(height: 17),
                    const _SectionTitle('Mes demandes récentes'),
                    const SizedBox(height: 8),
                    _RecentRequests(
                      items: _data?.tickets ?? const <SupportTicketSummary>[],
                      onTap: (ticket) async {
                        await Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => SupportTicketDetailScreen(ticketId: ticket.id),
                          ),
                        );
                        await _load();
                      },
                      onCreate: _newTicket,
                    ),
                    const SizedBox(height: 17),
                    const _SupportHoursCard(),
                  ],
                ),
              ),
      ),
      bottomNavigationBar: const OvanieBottomNavigation(
        selectedTab: OvanieMainTab.account,
      ),
    );
  }
}

class _Header extends StatelessWidget {
  final VoidCallback onBack;
  const _Header({required this.onBack});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 44,
          height: 40,
          child: IconButton(
            padding: EdgeInsets.zero,
            alignment: Alignment.centerLeft,
            onPressed: onBack,
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF071B67), size: 27),
          ),
        ),
        const SizedBox(height: 2),
        const Text(
          'Aide & Support',
          style: TextStyle(
            color: Color(0xFF071B67),
            fontSize: 29,
            height: 1.04,
            fontWeight: FontWeight.w900,
            letterSpacing: -.5,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'FAQ, contact et assistance',
          style: TextStyle(
            color: Color(0xFF24366F),
            fontSize: 14.5,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _AssistanceHero extends StatelessWidget {
  final Future<void> Function() onContact;
  const _AssistanceHero({required this.onContact});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
      decoration: BoxDecoration(
        color: const Color(0xFFFAFCFF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFD8E4FA)),
        boxShadow: const [
          BoxShadow(color: Color(0x0B1B3F7A), blurRadius: 14, offset: Offset(0, 5)),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0xFFE3E9F5)),
              boxShadow: const [
                BoxShadow(color: Color(0x0C072866), blurRadius: 9, offset: Offset(0, 3)),
              ],
            ),
            child: const Icon(Icons.headset_mic_outlined, color: Color(0xFF075DEB), size: 30),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Besoin d’assistance ?',
                  style: TextStyle(
                    color: Color(0xFF071B67),
                    fontSize: 15.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Notre équipe est disponible pour\nvous accompagner rapidement.',
                  style: TextStyle(
                    color: Color(0xFF24366F),
                    fontSize: 12.6,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          SizedBox(
            height: 44,
            child: FilledButton(
              onPressed: onContact,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 18),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text(
                'Nous contacter',
                style: TextStyle(fontSize: 13.2, fontWeight: FontWeight.w900),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const _ErrorBanner({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7F3),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFFFD3BC)),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline_rounded, size: 19, color: OvanieColors.orange),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Color(0xFF5E6471), fontSize: 11.5),
            ),
          ),
          TextButton(onPressed: onRetry, child: const Text('Réessayer')),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);

  @override
  Widget build(BuildContext context) => Text(
        text,
        style: const TextStyle(
          color: Color(0xFF071B67),
          fontSize: 15.2,
          fontWeight: FontWeight.w900,
        ),
      );
}

class _SectionTitleRow extends StatelessWidget {
  final VoidCallback onSeeAll;
  const _SectionTitleRow({required this.onSeeAll});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(child: _SectionTitle('Questions fréquentes')),
        InkWell(
          onTap: onSeeAll,
          borderRadius: BorderRadius.circular(8),
          child: const Padding(
            padding: EdgeInsets.symmetric(vertical: 4, horizontal: 2),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Voir tout',
                  style: TextStyle(
                    color: Color(0xFF075DEB),
                    fontSize: 13.1,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(width: 5),
                Icon(Icons.chevron_right_rounded, color: Color(0xFF075DEB), size: 22),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ContactChannels extends StatelessWidget {
  final Future<void> Function() onWhatsApp;
  final Future<void> Function() onPhone;
  final Future<void> Function() onChat;
  final Future<void> Function() onEmail;

  const _ContactChannels({
    required this.onWhatsApp,
    required this.onPhone,
    required this.onChat,
    required this.onEmail,
  });

  @override
  Widget build(BuildContext context) {
    final rows = <_ContactRowData>[
      _ContactRowData(
        icon: Icons.chat_rounded,
        iconColor: const Color(0xFF06A645),
        iconBackground: const Color(0xFFEAF9EE),
        title: 'WhatsApp',
        subtitle: 'Échangez rapidement avec notre équipe',
        onTap: onWhatsApp,
      ),
      _ContactRowData(
        icon: Icons.phone_rounded,
        iconColor: const Color(0xFF1265F5),
        iconBackground: const Color(0xFFEDF4FF),
        title: 'Appel téléphonique',
        subtitle: 'Appelez le support OVANIE',
        onTap: onPhone,
      ),
      _ContactRowData(
        icon: Icons.chat_bubble_rounded,
        iconColor: const Color(0xFF304DEA),
        iconBackground: const Color(0xFFEEF0FF),
        title: 'Chat support',
        subtitle: 'Discutez en direct avec un conseiller',
        onTap: onChat,
      ),
      _ContactRowData(
        icon: Icons.mail_outline_rounded,
        iconColor: const Color(0xFF1265F5),
        iconBackground: const Color(0xFFEDF4FF),
        title: 'E-mail',
        subtitle: 'Envoyez votre demande par e-mail',
        onTap: onEmail,
      ),
    ];

    return _GroupedCard(
      children: [
        for (var i = 0; i < rows.length; i++) ...[
          _ContactRow(data: rows[i]),
          if (i < rows.length - 1) const Divider(height: 1, color: Color(0xFFE8ECF3)),
        ],
      ],
    );
  }
}

class _ContactRowData {
  final IconData icon;
  final Color iconColor;
  final Color iconBackground;
  final String title;
  final String subtitle;
  final Future<void> Function() onTap;
  const _ContactRowData({
    required this.icon,
    required this.iconColor,
    required this.iconBackground,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });
}

class _ContactRow extends StatelessWidget {
  final _ContactRowData data;
  const _ContactRow({required this.data});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: data.onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 11, 8),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(color: data.iconBackground, borderRadius: BorderRadius.circular(10)),
              child: Icon(data.icon, color: data.iconColor, size: 25),
            ),
            const SizedBox(width: 13),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data.title,
                    style: const TextStyle(
                      color: Color(0xFF071B67),
                      fontSize: 14.3,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    data.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Color(0xFF4F6090), fontSize: 11.8),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF071B67), size: 24),
          ],
        ),
      ),
    );
  }
}

class _FaqQuickList extends StatelessWidget {
  final ValueChanged<String> onTap;
  const _FaqQuickList({required this.onTap});

  @override
  Widget build(BuildContext context) {
    const topics = [
      'Suivre ma commande',
      'Retour & remboursement',
      'Paiement sécurisé',
      'Adresses de livraison',
    ];
    return _GroupedCard(
      children: [
        for (var i = 0; i < topics.length; i++) ...[
          InkWell(
            onTap: () => onTap(topics[i]),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 10, 8),
              child: Row(
                children: [
                  Container(
                    width: 25,
                    height: 25,
                    decoration: const BoxDecoration(
                      color: Color(0xFFF1F6FF),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.help_outline_rounded, color: Color(0xFF1671F6), size: 17),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      topics[i],
                      style: const TextStyle(
                        color: Color(0xFF071B67),
                        fontSize: 13.4,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const Icon(Icons.chevron_right_rounded, color: Color(0xFF071B67), size: 23),
                ],
              ),
            ),
          ),
          if (i < topics.length - 1) const Divider(height: 1, color: Color(0xFFE8ECF3)),
        ],
      ],
    );
  }
}

class _RecentRequests extends StatelessWidget {
  final List<SupportTicketSummary> items;
  final ValueChanged<SupportTicketSummary> onTap;
  final Future<void> Function() onCreate;

  const _RecentRequests({required this.items, required this.onTap, required this.onCreate});

  @override
  Widget build(BuildContext context) {
    final recent = items.take(2).toList(growable: false);
    if (recent.isEmpty) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: const Color(0xFFE3E8F0)),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(color: const Color(0xFFF2F6FF), borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.receipt_long_outlined, color: Color(0xFF1265F5), size: 23),
            ),
            const SizedBox(width: 12),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Aucune demande récente', style: TextStyle(color: Color(0xFF071B67), fontSize: 13.7, fontWeight: FontWeight.w900)),
                  SizedBox(height: 3),
                  Text('Vous pouvez contacter notre équipe à tout moment.', style: TextStyle(color: Color(0xFF586992), fontSize: 11.5)),
                ],
              ),
            ),
            TextButton(onPressed: onCreate, child: const Text('Créer')),
          ],
        ),
      );
    }

    return _GroupedCard(
      children: [
        for (var i = 0; i < recent.length; i++) ...[
          _TicketRow(ticket: recent[i], onTap: () => onTap(recent[i])),
          if (i < recent.length - 1) const Divider(height: 1, color: Color(0xFFE8ECF3)),
        ],
      ],
    );
  }
}

class _TicketRow extends StatelessWidget {
  final SupportTicketSummary ticket;
  final VoidCallback onTap;
  const _TicketRow({required this.ticket, required this.onTap});

  String get _statusLabel {
    final value = ticket.status.toLowerCase();
    if (value.contains('resolv') || value.contains('clos') || value.contains('closed')) return 'Terminé';
    if (value.contains('cancel') || value.contains('reject') || value.contains('refus')) return 'Fermé';
    return 'En cours';
  }

  bool get _resolved => _statusLabel == 'Terminé';

  String _dateLabel(DateTime? date) {
    if (date == null) return '';
    final now = DateTime.now();
    if (now.year == date.year && now.month == date.month && now.day == date.day) return 'Aujourd’hui';
    const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }

  @override
  Widget build(BuildContext context) {
    final date = ticket.updatedAt ?? ticket.createdAt;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 9, 10, 9),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: _resolved ? const Color(0xFFECFAEF) : const Color(0xFFFFF2E8),
                borderRadius: BorderRadius.circular(11),
              ),
              child: Icon(
                _resolved ? Icons.check_circle_outline_rounded : Icons.receipt_long_outlined,
                color: _resolved ? const Color(0xFF19A54A) : OvanieColors.orange,
                size: 27,
              ),
            ),
            const SizedBox(width: 13),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    ticket.reference.isEmpty ? '#SUP-${ticket.id}' : '#${ticket.reference.replaceFirst('#', '')}',
                    style: const TextStyle(color: Color(0xFF071B67), fontSize: 13.7, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    ticket.subject,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Color(0xFF071B67), fontSize: 12.7, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    ticket.category.isEmpty ? 'Assistance client OVANIE' : _prettyCategory(ticket.category),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Color(0xFF5A6990), fontSize: 10.8),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: _resolved ? const Color(0xFFEAF8ED) : const Color(0xFFFFF0E3),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _statusLabel,
                    style: TextStyle(
                      color: _resolved ? const Color(0xFF118B3D) : OvanieColors.orange,
                      fontSize: 10.7,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  _dateLabel(date),
                  style: const TextStyle(color: Color(0xFF647198), fontSize: 10.6),
                ),
              ],
            ),
            const SizedBox(width: 5),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF071B67), size: 22),
          ],
        ),
      ),
    );
  }

  String _prettyCategory(String raw) {
    final value = raw.replaceAll('_', ' ').replaceAll('-', ' ').trim();
    if (value.isEmpty) return 'Assistance client OVANIE';
    return value[0].toUpperCase() + value.substring(1);
  }
}

class _SupportHoursCard extends StatelessWidget {
  const _SupportHoursCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 91,
      decoration: BoxDecoration(
        color: const Color(0xFFFAFCFF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFD8E4FA)),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          const Positioned(
            right: 0,
            top: 0,
            bottom: 0,
            width: 205,
            child: CustomPaint(painter: _SupportSkylinePainter()),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 13, 12, 13),
            child: Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFFE2E9F5)),
                  ),
                  child: const Icon(Icons.schedule_rounded, color: Color(0xFF075DEB), size: 25),
                ),
                const SizedBox(width: 13),
                const Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Horaires du support',
                        style: TextStyle(color: Color(0xFF071B67), fontSize: 13.8, fontWeight: FontWeight.w900),
                      ),
                      SizedBox(height: 3),
                      Text(
                        'Lundi à samedi · 8h à 18h\nRéponse rapide via WhatsApp et chat',
                        style: TextStyle(color: Color(0xFF24366F), fontSize: 11.4, height: 1.33),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SupportSkylinePainter extends CustomPainter {
  const _SupportSkylinePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF8DB7F7).withOpacity(.35)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    final fill = Paint()
      ..color = const Color(0xFFEDF4FF).withOpacity(.82)
      ..style = PaintingStyle.fill;

    canvas.drawRect(Rect.fromLTWH(size.width * .12, size.height * .55, 14, size.height * .45), fill);
    canvas.drawRect(Rect.fromLTWH(size.width * .24, size.height * .45, 17, size.height * .55), fill);
    canvas.drawRect(Rect.fromLTWH(size.width * .36, size.height * .62, 20, size.height * .38), fill);
    canvas.drawRect(Rect.fromLTWH(size.width * .48, size.height * .36, 14, size.height * .64), fill);

    for (final x in [size.width * .16, size.width * .28, size.width * .40, size.width * .52]) {
      canvas.drawLine(Offset(x, size.height * .55), Offset(x, size.height), paint);
    }

    final craneX = size.width * .68;
    canvas.drawLine(Offset(craneX, size.height * .16), Offset(craneX, size.height), paint);
    canvas.drawLine(Offset(craneX - 6, size.height * .22), Offset(size.width * .97, size.height * .22), paint);
    canvas.drawLine(Offset(craneX - 6, size.height * .22), Offset(craneX + 12, size.height * .10), paint);
    canvas.drawLine(Offset(craneX + 42, size.height * .22), Offset(craneX + 42, size.height * .51), paint);
    canvas.drawCircle(Offset(craneX + 42, size.height * .54), 2, paint);

    final ground = Path()
      ..moveTo(0, size.height - 4)
      ..lineTo(size.width, size.height - 4);
    canvas.drawPath(ground, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _GroupedCard extends StatelessWidget {
  final List<Widget> children;
  const _GroupedCard({required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: const Color(0xFFE3E8F0)),
        boxShadow: const [
          BoxShadow(color: Color(0x0715264A), blurRadius: 9, offset: Offset(0, 3)),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(children: children),
    );
  }
}
