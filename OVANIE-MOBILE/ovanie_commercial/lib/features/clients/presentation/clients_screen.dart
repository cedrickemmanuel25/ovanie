import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/navigation/commercial_tab_bus.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../../shops/data/shops_service.dart';
import '../data/clients_service.dart';
import '../models/client_data.dart';
import 'client_chrome.dart';
import 'create_client_screen.dart';

class CommercialClientsScreen extends StatefulWidget {
  const CommercialClientsScreen({
    super.key,
    required this.clientsService,
    required this.shopsService,
    required this.initialUser,
    required this.onLogout,
  });

  final ClientsService clientsService;
  final ShopsService shopsService;
  final Map<String, dynamic> initialUser;
  final Future<void> Function() onLogout;

  @override
  State<CommercialClientsScreen> createState() => _CommercialClientsScreenState();
}

class _CommercialClientsScreenState extends State<CommercialClientsScreen> {
  final TextEditingController _searchController = TextEditingController();
  CommercialClientsData? _data;
  ClientFilter _filter = ClientFilter.all;
  Timer? _debounce;
  bool _loading = true;
  String? _error;

  CommercialProfile get _profile =>
      _data?.profile ?? CommercialProfile.fromJson(widget.initialUser);

  int get _unreadNotifications => _data?.unreadNotifications ?? 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({bool preserve = true}) async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
      if (!preserve) _data = null;
    });

    try {
      final data = await widget.clientsService.fetch(
        query: _searchController.text,
        filter: _filter,
      );
      if (!mounted) return;
      setState(() => _data = data);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Impossible de charger les clients.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onSearchChanged(String _) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), _load);
  }

  Future<void> _selectFilter(ClientFilter filter) async {
    if (_filter == filter) return;
    setState(() => _filter = filter);
    await _load();
  }

  Future<void> _openCreateClient() async {
    final result = await Navigator.of(context).push<CreateCommercialClientResult>(
      MaterialPageRoute(
        builder: (_) => CommercialCreateClientScreen(
          clientsService: widget.clientsService,
          shopsService: widget.shopsService,
          initialUser: {
            ...widget.initialUser,
            'first_name': _profile.firstName,
            'name': _profile.name,
            'avatar_url': _profile.avatarUrl,
          },
          unreadNotifications: _unreadNotifications,
          onLogout: widget.onLogout,
        ),
      ),
    );

    if (result == null || !mounted) return;
    await _load();
    if (!mounted) return;

    final delivery = <String>[];
    if (result.emailSent) delivery.add('e-mail');
    if (result.smsSent) delivery.add('SMS');
    final suffix = delivery.isEmpty ? '' : ' Identifiants envoyés par ${delivery.join(' et ')}.';

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${result.message}$suffix'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _showProfileMenu() async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: const Color(0xFF071E43),
      showDragHandle: true,
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _profile.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Espace Commercial OVANIE',
                style: TextStyle(color: Color(0xFF9FB0CF)),
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'logout'),
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Se déconnecter'),
              ),
            ],
          ),
        ),
      ),
    );
    if (action == 'logout') await widget.onLogout();
  }

  void _futureModule(String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$title : cet écran sera relié dans le prochain lot.'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _handleBottomNavigation(int index) {
    if (index == 1) return;
    CommercialTabBus.request(index);
  }

  Future<void> _showFilters() async {
    final selected = await showModalBottomSheet<ClientFilter>(
      context: context,
      backgroundColor: const Color(0xFF071E43),
      showDragHandle: true,
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Filtrer les clients',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(height: 10),
              for (final filter in ClientFilter.values)
                ListTile(
                  onTap: () => Navigator.pop(context, filter),
                  leading: Icon(
                    _filter == filter ? Icons.radio_button_checked : Icons.radio_button_off,
                    color: _filter == filter ? OvanieColors.blue : const Color(0xFF9FB0CF),
                  ),
                  title: Text(
                    _filterLabel(filter),
                    style: const TextStyle(color: Colors.white),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
    if (selected != null) await _selectFilter(selected);
  }

  String _filterLabel(ClientFilter filter) => switch (filter) {
        ClientFilter.all => 'Tous',
        ClientFilter.active => 'Actifs',
        ClientFilter.prospect => 'Prospects',
        ClientFilter.inactive => 'Inactifs',
      };

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.78, 1.08).toDouble();
    double s(double value) => value * scale;
    final data = _data;
    final summary = data?.summary ?? ClientSummary.empty;
    final clients = data?.clients ?? const <CommercialClient>[];

    return Scaffold(
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: RefreshIndicator(
            onRefresh: _load,
            color: OvanieColors.blue,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverPadding(
                  padding: EdgeInsets.fromLTRB(s(16), s(9), s(16), s(100)),
                  sliver: SliverList(
                    delegate: SliverChildListDelegate.fixed([
                      CommercialClientsHeader(
                        profile: _profile,
                        unreadNotifications: _unreadNotifications,
                        scale: scale,
                        onNotificationsTap: () => _futureModule('Notifications'),
                        onAvatarTap: _showProfileMenu,
                      ),
                      SizedBox(height: s(14)),
                      CommercialClientSearch(
                        controller: _searchController,
                        scale: scale,
                        onChanged: _onSearchChanged,
                        onFilterTap: _showFilters,
                      ),
                      SizedBox(height: s(18)),
                      _TitleRow(
                        scale: scale,
                        onCreate: _openCreateClient,
                      ),
                      SizedBox(height: s(17)),
                      _FilterRow(
                        scale: scale,
                        selected: _filter,
                        summary: summary,
                        onSelected: _selectFilter,
                      ),
                      SizedBox(height: s(15)),
                      if (_error != null) ...[
                        _ErrorBanner(
                          message: _error!,
                          scale: scale,
                          onRetry: _load,
                        ),
                        SizedBox(height: s(12)),
                      ],
                      if (_loading && data == null)
                        Padding(
                          padding: EdgeInsets.symmetric(vertical: s(18)),
                          child: const Center(
                            child: CircularProgressIndicator(strokeWidth: 2.4),
                          ),
                        )
                      else if (clients.isEmpty)
                        _EmptyClients(
                          scale: scale,
                          query: _searchController.text,
                          onCreate: _openCreateClient,
                        )
                      else
                        ...clients.map(
                          (client) => Padding(
                            padding: EdgeInsets.only(bottom: s(10)),
                            child: _ClientCard(
                              client: client,
                              scale: scale,
                              onTap: () => _futureModule('Fiche client'),
                              onMenuTap: () => _futureModule('Actions client'),
                            ),
                          ),
                        ),
                    ]),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      extendBody: true,
      bottomNavigationBar: CommercialBottomNavigation(
        scale: scale,
        currentIndex: 1,
        onTap: _handleBottomNavigation,
      ),
    );
  }
}

class _TitleRow extends StatelessWidget {
  const _TitleRow({required this.scale, required this.onCreate});

  final double scale;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Mes clients',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: s(22.5),
                  fontWeight: FontWeight.w800,
                  letterSpacing: -.45,
                ),
              ),
              SizedBox(height: s(5)),
              Text(
                'Gérez vos clients et suivez leur activité',
                style: TextStyle(
                  color: const Color(0xFFC2CCDD),
                  fontSize: s(11.3),
                ),
              ),
            ],
          ),
        ),
        SizedBox(width: s(8)),
        SizedBox(
          height: s(43),
          child: FilledButton.icon(
            onPressed: onCreate,
            style: FilledButton.styleFrom(
              backgroundColor: OvanieColors.orange,
              foregroundColor: Colors.white,
              padding: EdgeInsets.symmetric(horizontal: s(14)),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(s(12)),
              ),
              elevation: 0,
            ),
            icon: Icon(Icons.add_rounded, size: s(23)),
            label: Text(
              'Créer un client',
              style: TextStyle(fontSize: s(12.2), fontWeight: FontWeight.w600),
            ),
          ),
        ),
      ],
    );
  }
}

class _FilterRow extends StatelessWidget {
  const _FilterRow({
    required this.scale,
    required this.selected,
    required this.summary,
    required this.onSelected,
  });

  final double scale;
  final ClientFilter selected;
  final ClientSummary summary;
  final ValueChanged<ClientFilter> onSelected;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final items = [
      (ClientFilter.all, 'Tous', summary.all, const Color(0xFF1E82FF)),
      (ClientFilter.active, 'Actifs', summary.active, const Color(0xFF63E98A)),
      (ClientFilter.prospect, 'Prospects', summary.prospects, const Color(0xFF955CFF)),
      (ClientFilter.inactive, 'Inactifs', summary.inactive, const Color(0xFFAAB8CF)),
    ];

    return Row(
      children: List.generate(items.length, (index) {
        final item = items[index];
        final active = item.$1 == selected;
        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: index == items.length - 1 ? 0 : s(7)),
            child: InkWell(
              onTap: () => onSelected(item.$1),
              borderRadius: BorderRadius.circular(s(21)),
              child: Container(
                height: s(40),
                padding: EdgeInsets.symmetric(horizontal: s(8)),
                decoration: BoxDecoration(
                  color: active
                      ? const Color(0xFF0B2F67).withOpacity(.96)
                      : const Color(0xFF0B2852).withOpacity(.82),
                  borderRadius: BorderRadius.circular(s(21)),
                  border: Border.all(
                    color: active ? const Color(0xFF147BFF) : const Color(0xFF284A73),
                    width: active ? 1 : .7,
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      item.$1 == ClientFilter.all ? Icons.groups_2_rounded : Icons.circle,
                      size: item.$1 == ClientFilter.all ? s(17) : s(8),
                      color: item.$4,
                    ),
                    SizedBox(width: s(5)),
                    Flexible(
                      child: Text(
                        item.$2,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: const Color(0xFFE9EFFA),
                          fontSize: s(10.2),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                    SizedBox(width: s(5)),
                    Container(
                      constraints: BoxConstraints(minWidth: s(20)),
                      padding: EdgeInsets.symmetric(horizontal: s(5), vertical: s(2)),
                      decoration: BoxDecoration(
                        color: active
                            ? const Color(0xFF174E92)
                            : const Color(0xFF213D64),
                        borderRadius: BorderRadius.circular(s(10)),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        '${item.$3}',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: s(9),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      }),
    );
  }
}

class _ClientCard extends StatelessWidget {
  const _ClientCard({
    required this.client,
    required this.scale,
    required this.onTap,
    required this.onMenuTap,
  });

  final CommercialClient client;
  final double scale;
  final VoidCallback onTap;
  final VoidCallback onMenuTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final paletteIndex = client.id.abs() % _avatarPalettes.length;
    final palette = _avatarPalettes[paletteIndex];
    final statusColor = switch (client.status) {
      'prospect' => const Color(0xFFB56700),
      'inactive' => const Color(0xFF243047),
      _ => const Color(0xFF079647),
    };
    final statusBg = switch (client.status) {
      'prospect' => const Color(0xFFFFF0C7),
      'inactive' => const Color(0xFFE9EDF3),
      _ => const Color(0xFFD9F7E5),
    };

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(s(12)),
        child: Container(
          constraints: BoxConstraints(minHeight: s(90)),
          padding: EdgeInsets.fromLTRB(s(12), s(11), s(10), s(11)),
          decoration: BoxDecoration(
            color: const Color(0xFFFBFBFC),
            borderRadius: BorderRadius.circular(s(12)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(.10),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: s(54),
                height: s(54),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: palette.$1,
                ),
                alignment: Alignment.center,
                child: Text(
                  client.initials,
                  style: TextStyle(
                    color: palette.$2,
                    fontSize: s(18),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              SizedBox(width: s(12)),
              Expanded(
                flex: 5,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: s(7),
                      runSpacing: s(4),
                      children: [
                        Text(
                          client.name.isEmpty ? 'Client OVANIE' : client.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: const Color(0xFF060D1D),
                            fontSize: s(14),
                            fontWeight: FontWeight.w800,
                            letterSpacing: -.2,
                          ),
                        ),
                        Container(
                          padding: EdgeInsets.symmetric(horizontal: s(8), vertical: s(4)),
                          decoration: BoxDecoration(
                            color: client.isEnterprise
                                ? const Color(0xFFFFEBD9)
                                : const Color(0xFFE1EEFF),
                            borderRadius: BorderRadius.circular(s(13)),
                          ),
                          child: Text(
                            client.accountTypeLabel,
                            style: TextStyle(
                              color: client.isEnterprise
                                  ? const Color(0xFFF25D13)
                                  : const Color(0xFF0564E8),
                              fontSize: s(8.5),
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: s(7)),
                    _ClientLine(
                      scale: scale,
                      icon: Icons.phone_rounded,
                      text: _displayPhone(client.phone),
                    ),
                    SizedBox(height: s(5)),
                    _ClientLine(
                      scale: scale,
                      icon: Icons.location_on_rounded,
                      text: client.locationLabel,
                    ),
                  ],
                ),
              ),
              SizedBox(width: s(8)),
              SizedBox(
                width: s(112),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: EdgeInsets.symmetric(horizontal: s(10), vertical: s(5)),
                          decoration: BoxDecoration(
                            color: statusBg,
                            borderRadius: BorderRadius.circular(s(14)),
                          ),
                          child: Text(
                            client.statusLabel,
                            style: TextStyle(
                              color: statusColor,
                              fontSize: s(9),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const Spacer(),
                        InkWell(
                          onTap: onMenuTap,
                          customBorder: const CircleBorder(),
                          child: Icon(
                            Icons.more_vert_rounded,
                            color: const Color(0xFF1B3869),
                            size: s(19),
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: s(10)),
                    Row(
                      children: [
                        Icon(Icons.shopping_cart_outlined, color: const Color(0xFF294B81), size: s(17)),
                        SizedBox(width: s(6)),
                        Expanded(
                          child: Text(
                            '${client.ordersCount} commande${client.ordersCount > 1 ? 's' : ''}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: const Color(0xFF273F6A),
                              fontSize: s(9.3),
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                        Icon(Icons.chevron_right_rounded, color: const Color(0xFF193767), size: s(20)),
                      ],
                    ),
                    Padding(
                      padding: EdgeInsets.only(left: s(23), top: s(2)),
                      child: Text(
                        client.lastOrderAt == null
                            ? 'Aucune commande'
                            : 'Dernière : ${_formatDate(client.lastOrderAt!)}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: const Color(0xFF53678E),
                          fontSize: s(7.5),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static const _avatarPalettes = [
    (Color(0xFFDDEEFF), Color(0xFF0872E8)),
    (Color(0xFFFFECD5), Color(0xFFF05A13)),
    (Color(0xFFDFF8E9), Color(0xFF109144)),
    (Color(0xFFECE3FF), Color(0xFF6631D6)),
    (Color(0xFFFFDDEB), Color(0xFFD8165D)),
  ];

  static String _displayPhone(String value) {
    final digits = value.replaceAll(RegExp(r'\D'), '');
    var local = digits;
    if (digits.startsWith('225') && digits.length > 10) {
      local = digits.substring(3);
    }
    if (local.length == 10) {
      return '${local.substring(0, 2)} ${local.substring(2, 4)} ${local.substring(4, 6)} ${local.substring(6, 8)} ${local.substring(8, 10)}';
    }
    return value.isEmpty ? 'Téléphone non renseigné' : value;
  }

  static String _formatDate(DateTime date) {
    const months = [
      'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
      'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.',
    ];
    final local = date.toLocal();
    return '${local.day.toString().padLeft(2, '0')} ${months[local.month - 1]} ${local.year}';
  }
}

class _ClientLine extends StatelessWidget {
  const _ClientLine({required this.scale, required this.icon, required this.text});

  final double scale;
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: const Color(0xFF3D5B8D), size: 14 * scale),
        SizedBox(width: 7 * scale),
        Expanded(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: const Color(0xFF405986), fontSize: 9.5 * scale),
          ),
        ),
      ],
    );
  }
}

class _EmptyClients extends StatelessWidget {
  const _EmptyClients({required this.scale, required this.query, required this.onCreate});

  final double scale;
  final String query;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.all(s(24)),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FAFC),
        borderRadius: BorderRadius.circular(s(14)),
      ),
      child: Column(
        children: [
          Icon(Icons.groups_2_outlined, color: const Color(0xFF187CEF), size: s(42)),
          SizedBox(height: s(10)),
          Text(
            query.trim().isEmpty ? 'Aucun client pour ce filtre' : 'Aucun client trouvé',
            style: TextStyle(
              color: OvanieColors.ink,
              fontSize: s(14),
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: s(5)),
          Text(
            query.trim().isEmpty
                ? 'Les clients créés par ce commercial apparaîtront ici.'
                : 'Modifiez votre recherche ou le filtre sélectionné.',
            textAlign: TextAlign.center,
            style: TextStyle(color: const Color(0xFF66728A), fontSize: s(10)),
          ),
          if (query.trim().isEmpty) ...[
            SizedBox(height: s(14)),
            FilledButton.icon(
              onPressed: onCreate,
              style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Créer un client'),
            ),
          ],
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message, required this.scale, required this.onRetry});

  final String message;
  final double scale;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.symmetric(horizontal: s(12), vertical: s(9)),
      decoration: BoxDecoration(
        color: const Color(0xFF4D1820).withOpacity(.92),
        borderRadius: BorderRadius.circular(s(10)),
        border: Border.all(color: const Color(0xFFA7474F).withOpacity(.65)),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, color: const Color(0xFFFFB4B8), size: s(18)),
          SizedBox(width: s(8)),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: Colors.white, fontSize: s(10.2), height: 1.25),
            ),
          ),
          TextButton(
            onPressed: onRetry,
            child: Text('Réessayer', style: TextStyle(fontSize: s(10.2))),
          ),
        ],
      ),
    );
  }
}
