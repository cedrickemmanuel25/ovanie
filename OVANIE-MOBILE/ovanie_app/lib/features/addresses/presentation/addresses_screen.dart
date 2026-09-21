import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/addresses_repository.dart';
import '../domain/client_address.dart';
import 'address_form_screen.dart';

class AddressesScreen extends StatefulWidget {
  final bool selectionMode;
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;

  const AddressesScreen({
    super.key,
    this.selectionMode = false,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenCart,
    this.onOpenFavorites,
  });

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  final _repository = const AddressesRepository();
  bool _loading = true;
  bool _mutating = false;
  String? _error;
  List<ClientAddress> _addresses = const [];
  List<AddressTypeOption> _types = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final payload = await _repository.fetch();
      if (!mounted) return;
      setState(() {
        _addresses = payload.addresses;
        _types = payload.types;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm({
    ClientAddress? existing,
    bool useCurrentPosition = false,
  }) async {
    final saved = await Navigator.of(context).push<ClientAddress>(
      MaterialPageRoute<ClientAddress>(
        builder: (_) => AddressFormScreen(
          existing: existing,
          types: _types,
          startWithCurrentPosition: useCurrentPosition,
        ),
      ),
    );
    if (saved == null || !mounted) return;
    await _load();
  }

  Future<void> _delete(ClientAddress address) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer cette adresse ?'),
        content: Text('« ${address.label} » sera retirée de vos adresses enregistrées.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: OvanieColors.danger),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _mutating = true);
    try {
      await _repository.delete(address.id);
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _setDefault(ClientAddress address) async {
    if (address.isDefault || _mutating) return;
    setState(() => _mutating = true);
    try {
      await _repository.setDefault(address.id);
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _select(ClientAddress address) {
    if (widget.selectionMode) Navigator.of(context).pop(address);
  }

  void _openSupport() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()),
    );
  }

  void _leaveFor(VoidCallback? destination) {
    if (destination == null) return;
    Navigator.of(context).pop();
    destination();
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.selectionMode ? 'Choisir une adresse' : 'Mes adresses';
    final isEmpty = !_loading && _error == null && _addresses.isEmpty;

    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: widget.selectionMode
          ? null
          : const OvanieBottomNavigation(
              selectedTab: OvanieMainTab.account,
            ),
      body: SafeArea(
        bottom: widget.selectionMode,
        child: RefreshIndicator(
          color: OvanieColors.orange,
          onRefresh: _load,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
                  child: _Header(
                    title: title,
                    onBack: () => Navigator.maybePop(context),
                    onAdd: _mutating ? null : () => _openForm(),
                    selectionMode: widget.selectionMode,
                  ),
                ),
              ),
              if (!widget.selectionMode)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(54, 4, 18, 0),
                    child: Text(
                      'Gérez vos adresses de livraison, chantier, bureau et dépôt.',
                      style: TextStyle(
                        color: Color(0xFF18326B),
                        fontSize: 12.2,
                        height: 1.35,
                      ),
                    ),
                  ),
                ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
                  child: _InfoBanner(
                    text: widget.selectionMode
                        ? 'Sélectionnez l’adresse à utiliser pour votre livraison.'
                        : 'L’adresse principale est utilisée par défaut lors du passage en caisse.',
                  ),
                ),
              ),
              if (!widget.selectionMode)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(18, 16, 18, 0),
                    child: Row(
                      children: [
                        Expanded(
                          child: _PrimaryActionButton(
                            icon: Icons.add_rounded,
                            label: 'Ajouter une adresse',
                            onTap: _mutating ? null : () => _openForm(),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _OutlineActionButton(
                            icon: Icons.location_on_outlined,
                            label: 'Utiliser ma position',
                            onTap: _mutating
                                ? null
                                : () => _openForm(useCurrentPosition: true),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              if (_error != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
                    child: _ErrorBox(message: _error!),
                  ),
                ),
              if (_loading)
                const SliverToBoxAdapter(
                  child: SizedBox(
                    height: 420,
                    child: Center(
                      child: CircularProgressIndicator(color: OvanieColors.orange),
                    ),
                  ),
                )
              else if (isEmpty)
                SliverToBoxAdapter(
                  child: _EmptyAddresses(
                    onAdd: () => _openForm(),
                    onPosition: () => _openForm(useCurrentPosition: true),
                    onSupport: _openSupport,
                    compact: widget.selectionMode,
                  ),
                )
              else
                SliverPadding(
                  padding: EdgeInsets.fromLTRB(
                    18,
                    widget.selectionMode ? 14 : 12,
                    18,
                    18,
                  ),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, rawIndex) {
                        final itemCount = _addresses.length + (widget.selectionMode ? 0 : 1);
                        if (rawIndex.isOdd) return const SizedBox(height: 9);
                        final index = rawIndex ~/ 2;
                        if (index >= itemCount) return null;
                        if (index == _addresses.length) {
                          return _SupportCard(onTap: _openSupport);
                        }
                        final address = _addresses[index];
                        return _AddressCard(
                          address: address,
                          selectionMode: widget.selectionMode,
                          disabled: _mutating,
                          onSelect: () => _select(address),
                          onEdit: () => _openForm(existing: address),
                          onDelete: () => _delete(address),
                          onDefault: () => _setDefault(address),
                        );
                      },
                      childCount: (_addresses.length + (widget.selectionMode ? 0 : 1)) * 2 - 1,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  final String title;
  final VoidCallback onBack;
  final VoidCallback? onAdd;
  final bool selectionMode;

  const _Header({
    required this.title,
    required this.onBack,
    required this.onAdd,
    required this.selectionMode,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        InkWell(
          onTap: onBack,
          borderRadius: BorderRadius.circular(28),
          child: const Padding(
            padding: EdgeInsets.all(4),
            child: Icon(Icons.arrow_back_rounded, size: 21, color: Color(0xFF041A57)),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: Color(0xFF061A56),
              fontSize: 21,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
            ),
          ),
        ),
        if (!selectionMode)
          InkWell(
            onTap: onAdd,
            borderRadius: BorderRadius.circular(28),
            child: const Padding(
              padding: EdgeInsets.all(5),
              child: Icon(Icons.add_location_alt_outlined, size: 21, color: Color(0xFF061A56)),
            ),
          ),
      ],
    );
  }
}

class _InfoBanner extends StatelessWidget {
  final String text;
  const _InfoBanner({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F9FD),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline_rounded, color: Color(0xFF061A56), size: 21),
          const SizedBox(width: 7),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                color: Color(0xFF17306A),
                fontSize: 12.2,
                height: 1.35,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PrimaryActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  const _PrimaryActionButton({required this.icon, required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: FilledButton.icon(
        onPressed: onTap,
        style: FilledButton.styleFrom(
          backgroundColor: const Color(0xFFFF4C0A),
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontSize: 12.8, fontWeight: FontWeight.w900),
        ),
        icon: Icon(icon, size: 22),
        label: FittedBox(fit: BoxFit.scaleDown, child: Text(label, maxLines: 1)),
      ),
    );
  }
}

class _OutlineActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  const _OutlineActionButton({required this.icon, required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: OutlinedButton.icon(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          foregroundColor: const Color(0xFF061A56),
          side: const BorderSide(color: Color(0xFFB7C4E4), width: 1.2),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontSize: 12.8, fontWeight: FontWeight.w900),
        ),
        icon: Icon(icon, size: 21),
        label: FittedBox(fit: BoxFit.scaleDown, child: Text(label, maxLines: 1)),
      ),
    );
  }
}

class _AddressCard extends StatelessWidget {
  final ClientAddress address;
  final bool selectionMode;
  final bool disabled;
  final VoidCallback onSelect;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onDefault;

  const _AddressCard({
    required this.address,
    required this.selectionMode,
    required this.disabled,
    required this.onSelect,
    required this.onEdit,
    required this.onDelete,
    required this.onDefault,
  });

  IconData get _icon => switch (address.type) {
        'office' => Icons.apartment_rounded,
        'site' => Icons.construction_rounded,
        'warehouse' => Icons.warehouse_rounded,
        _ => Icons.home_outlined,
      };

  Color get _accent => switch (address.type) {
        'office' => const Color(0xFF6137D6),
        'site' => const Color(0xFF153A99),
        'warehouse' => const Color(0xFF07963F),
        _ => const Color(0xFFFF6500),
      };

  Color get _accentBg => switch (address.type) {
        'office' => const Color(0xFFF0EBFF),
        'site' => const Color(0xFFEAF1FF),
        'warehouse' => const Color(0xFFE8F7ED),
        _ => const Color(0xFFFFF0E5),
      };

  @override
  Widget build(BuildContext context) {
    final phone = address.phone.trim().isEmpty ? '' : formatCiPhoneDisplay(address.phone);
    final displayedAddress = address.address.trim().isNotEmpty
        ? address.address.trim()
        : address.locationSummary;

    return InkWell(
      onTap: selectionMode ? onSelect : null,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE7ECF4)),
          boxShadow: const [
            BoxShadow(
              color: Color(0x0D0A1D4D),
              blurRadius: 18,
              offset: Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 62,
                    height: 62,
                    decoration: BoxDecoration(
                      color: _accentBg,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(_icon, color: _accent, size: 32),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 9,
                          runSpacing: 7,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(
                              address.label,
                              style: const TextStyle(
                                color: Color(0xFF061A56),
                                fontSize: 16.5,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            if (address.isDefault)
                              const _Pill(
                                label: '★ Par défaut',
                                foreground: Colors.white,
                                background: Color(0xFF1554E8),
                              ),
                            _Pill(
                              label: address.typeLabel,
                              foreground: _accent,
                              background: _accentBg,
                            ),
                          ],
                        ),
                        const SizedBox(height: 3),
                        if (address.recipientName.trim().isNotEmpty)
                          _MetaLine(icon: Icons.person_outline_rounded, text: address.recipientName),
                        if (phone.isNotEmpty) ...[
                          const SizedBox(height: 3),
                          _MetaLine(icon: Icons.phone_outlined, text: phone),
                        ],
                        if (displayedAddress.isNotEmpty) ...[
                          const SizedBox(height: 3),
                          _MetaLine(
                            icon: Icons.location_on_outlined,
                            text: displayedAddress,
                            maxLines: 3,
                          ),
                        ],
                      ],
                    ),
                  ),
                  Icon(
                    selectionMode ? Icons.chevron_right_rounded : Icons.keyboard_arrow_down_rounded,
                    color: const Color(0xFF061A56),
                    size: 24,
                  ),
                ],
              ),
            ),
            if (!selectionMode)
              Container(
                height: 40,
                decoration: const BoxDecoration(
                  border: Border(top: BorderSide(color: Color(0xFFE5EAF2))),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: _CardAction(
                        icon: Icons.edit_outlined,
                        label: 'Modifier',
                        color: const Color(0xFF0A4CDA),
                        onTap: disabled ? null : onEdit,
                      ),
                    ),
                    const VerticalDivider(width: 1, color: Color(0xFFE5EAF2)),
                    Expanded(
                      child: _CardAction(
                        icon: Icons.delete_outline_rounded,
                        label: 'Supprimer',
                        color: const Color(0xFFFF1F2C),
                        onTap: disabled ? null : onDelete,
                      ),
                    ),
                    if (!address.isDefault) ...[
                      const VerticalDivider(width: 1, color: Color(0xFFE5EAF2)),
                      Expanded(
                        child: _CardAction(
                          icon: Icons.star_border_rounded,
                          label: 'Définir comme principale',
                          color: const Color(0xFF0A4CDA),
                          onTap: disabled ? null : onDefault,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  final String label;
  final Color foreground;
  final Color background;

  const _Pill({required this.label, required this.foreground, required this.background});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(8)),
      child: Text(
        label,
        style: TextStyle(color: foreground, fontSize: 10.5, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _MetaLine extends StatelessWidget {
  final IconData icon;
  final String text;
  final int maxLines;

  const _MetaLine({required this.icon, required this.text, this.maxLines = 1});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: const Color(0xFF0B2A6A), size: 15),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            text,
            maxLines: maxLines,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF18326B),
              fontSize: 12.0,
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }
}

class _CardAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback? onTap;

  const _CardAction({required this.icon, required this.label, required this.color, this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 5),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 15, color: color),
            const SizedBox(width: 7),
            Flexible(
              child: Text(
                label,
                maxLines: 2,
                textAlign: TextAlign.center,
                style: TextStyle(color: color, fontSize: 10.8, fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyAddresses extends StatelessWidget {
  final VoidCallback onAdd;
  final VoidCallback onPosition;
  final VoidCallback onSupport;
  final bool compact;

  const _EmptyAddresses({
    required this.onAdd,
    required this.onPosition,
    required this.onSupport,
    required this.compact,
  });

  @override
  Widget build(BuildContext context) {
    if (compact) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(18, 28, 18, 24),
        child: Column(
          children: [
            Image.asset(
              'assets/images/address_empty_illustration.png',
              width: 220,
              fit: BoxFit.contain,
            ),
            const SizedBox(height: 18),
            const Text(
              'Aucune adresse enregistrée',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Color(0xFF061A56),
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 11),
            const Text(
              'Ajoutez une adresse pour continuer.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Color(0xFF53658F), fontSize: 12.8),
            ),
            const SizedBox(height: 22),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 320),
              child: _PrimaryActionButton(
                icon: Icons.add_rounded,
                label: 'Ajouter une adresse',
                onTap: onAdd,
              ),
            ),
          ],
        ),
      );
    }

    final viewport = MediaQuery.sizeOf(context);
    final illustrationWidth = (viewport.width * .61).clamp(218.0, 292.0).toDouble();

    // IMPORTANT: this widget is rendered inside a SliverToBoxAdapter.
    // A Column + Spacer with an unbounded sliver height makes Flutter stop
    // laying out the empty-state content on some physical devices. Give the
    // empty state a real viewport-relative height so the illustration, texts
    // and support block are always visible, exactly like the reference screen.
    final emptyHeight = (viewport.height - 292.0).clamp(505.0, 660.0).toDouble();

    return SizedBox(
      height: emptyHeight,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 12),
        child: Column(
          children: [
            const SizedBox(height: 12),
            Image.asset(
              'assets/images/address_empty_illustration.png',
              width: illustrationWidth,
              fit: BoxFit.contain,
              filterQuality: FilterQuality.high,
            ),
            const SizedBox(height: 17),
            const Text(
              'Aucune adresse enregistrée',
              textAlign: TextAlign.center,
              maxLines: 1,
              style: TextStyle(
                color: Color(0xFF061A56),
                fontSize: 22.0,
                height: 1.08,
                fontWeight: FontWeight.w900,
                letterSpacing: -.3,
              ),
            ),
            const SizedBox(height: 13),
            const Text(
              'Vous n’avez pas encore ajouté d’adresse.\n'
              'Utilisez les actions ci-dessus pour ajouter\n'
              'votre première adresse.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Color(0xFF53658F),
                fontSize: 13.4,
                height: 1.55,
                fontWeight: FontWeight.w500,
              ),
            ),
            const Spacer(),
            _SupportCard(onTap: onSupport),
          ],
        ),
      ),
    );
  }
}

class _SupportCard extends StatelessWidget {
  final VoidCallback onTap;
  const _SupportCard({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FBFF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFD8E3FB)),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.help_outline_rounded, color: Color(0xFF1557E8), size: 24),
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Besoin d’aide pour vos adresses ?',
                  style: TextStyle(
                    color: Color(0xFF061A56),
                    fontSize: 12.8,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Notre équipe est là pour vous accompagner.',
                  style: TextStyle(color: Color(0xFF53658F), fontSize: 12.2),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            onPressed: onTap,
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFFFF4C0A),
              side: const BorderSide(color: Color(0xFFFF4C0A)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            ),
            child: const Text('Nous contacter', style: TextStyle(fontWeight: FontWeight.w900)),
          ),
        ],
      ),
    );
  }
}

class _ErrorBox extends StatelessWidget {
  final String message;
  const _ErrorBox({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFFC8C8)),
      ),
      child: Text(
        message,
        style: const TextStyle(color: Color(0xFFB42318), fontWeight: FontWeight.w700),
      ),
    );
  }
}
