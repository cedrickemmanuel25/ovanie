import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/addresses_repository.dart';
import '../domain/client_address.dart';
import 'address_form_screen.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);

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
      _loading = _addresses.isEmpty;
      _error = null;
    });
    try {
      final payload = await _repository.fetch();
      if (!mounted) return;
      setState(() {
        // L'adresse principale est toujours présentée en premier.
        _addresses = [...payload.addresses]..sort((a, b) => a.isDefault == b.isDefault ? 0 : (a.isDefault ? -1 : 1));
        _types = payload.types;
      });
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm({ClientAddress? existing, bool useCurrentPosition = false}) async {
    final saved = await Navigator.of(context).push<ClientAddress>(
      MaterialPageRoute<ClientAddress>(
        builder: (_) =>
            AddressFormScreen(existing: existing, types: _types, startWithCurrentPosition: useCurrentPosition),
      ),
    );
    if (saved == null || !mounted) return;
    showOvanieToast(context, existing == null ? 'Adresse ajoutée.' : 'Adresse mise à jour.');
    await _load();
  }

  Future<void> _delete(ClientAddress address) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => _DeleteDialog(label: address.label),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _mutating = true);
    try {
      await _repository.delete(address.id);
      if (!mounted) return;
      showOvanieToast(context, 'Adresse supprimée.');
      await _load();
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  Future<void> _setDefault(ClientAddress address) async {
    if (address.isDefault || _mutating) return;
    setState(() => _mutating = true);
    try {
      await _repository.setDefault(address.id);
      if (!mounted) return;
      showOvanieToast(context, '« ${address.label} » est votre adresse principale.');
      await _load();
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selection = widget.selectionMode;
    final empty = !_loading && _error == null && _addresses.isEmpty;
    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: selection ? null : const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
      body: SafeArea(
        bottom: selection,
        child: RefreshIndicator(
          color: OvanieColors.orange,
          onRefresh: _load,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: _Header(
                  title: selection ? 'Choisir une adresse' : 'Mes adresses',
                  subtitle: selection
                      ? 'Sélectionnez l’adresse à utiliser pour votre livraison.'
                      : 'Gérez vos adresses de livraison, chantier, bureau et dépôt.',
                ),
              ),
              if (!selection || empty)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(14, 16, 14, 0),
                    child: Row(
                      children: [
                        Expanded(
                          flex: 21,
                          child: _ActionButton(
                            icon: Icons.add_rounded,
                            label: 'Ajouter une adresse',
                            filled: true,
                            onTap: _mutating ? null : () => _openForm(),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          flex: 19,
                          child: _ActionButton(
                            icon: Icons.gps_fixed_rounded,
                            label: 'Utiliser ma position',
                            filled: false,
                            onTap: _mutating ? null : () => _openForm(useCurrentPosition: true),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              if (!selection && !empty && !_loading)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(16, 10, 16, 0),
                    child: Text(
                      'L’adresse principale est utilisée par défaut lors du passage en caisse.',
                      style: TextStyle(color: _muted, fontSize: 12.5, height: 1.35),
                    ),
                  ),
                ),
              if (_loading)
                const SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                )
              else if (_error != null && _addresses.isEmpty)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: _ErrorState(message: _error!, onRetry: _load),
                )
              else if (empty)
                const SliverFillRemaining(hasScrollBody: false, child: _EmptyAddresses())
              else ...[
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
                  sliver: SliverList.separated(
                    itemCount: _addresses.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 14),
                    itemBuilder: (context, index) {
                      final address = _addresses[index];
                      return _AddressCard(
                        address: address,
                        selectionMode: selection,
                        disabled: _mutating,
                        onSelect: () => Navigator.of(context).pop(address),
                        onEdit: () => _openForm(existing: address),
                        onDelete: () => _delete(address),
                        onDefault: () => _setDefault(address),
                      );
                    },
                  ),
                ),
                if (!selection)
                  const SliverToBoxAdapter(
                    child: Padding(padding: EdgeInsets.fromLTRB(14, 14, 14, 18), child: _PrecisionTip()),
                  )
                else
                  const SliverToBoxAdapter(child: SizedBox(height: 18)),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 6, 4, 0),
      child: Stack(
        children: [
          IconButton(
            onPressed: () => Navigator.maybePop(context),
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(44, 8, 44, 0),
            child: Column(
              children: [
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: _muted, fontSize: 12.5, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.filled, this.onTap});

  final IconData icon;
  final String label;
  final bool filled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final content = Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(icon, size: filled ? 22 : 20),
        const SizedBox(width: 7),
        Flexible(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(label, maxLines: 1, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
          ),
        ),
      ],
    );
    final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(10));
    const padding = EdgeInsets.symmetric(horizontal: 8);
    return SizedBox(
      height: 46,
      child: filled
          ? FilledButton(
              onPressed: onTap,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                foregroundColor: Colors.white,
                padding: padding,
                shape: shape,
              ),
              child: content,
            )
          : OutlinedButton(
              onPressed: onTap,
              style: OutlinedButton.styleFrom(
                foregroundColor: _navy,
                side: const BorderSide(color: _navy, width: 1.3),
                padding: padding,
                shape: shape,
              ),
              child: content,
            ),
    );
  }
}

/// Couleurs de l'étiquette de type (Chantier, Bureau, Dépôt…).
(Color, Color) _typeColors(String type) => switch (type) {
  'site' => (const Color(0xFFFFEDE2), const Color(0xFFC2410C)),
  'office' => (const Color(0xFFE8F1FF), const Color(0xFF0B2E6B)),
  'warehouse' => (const Color(0xFFE5F6EB), const Color(0xFF15803D)),
  'home' => (const Color(0xFFF1EBFF), const Color(0xFF5B34B5)),
  _ => (const Color(0xFFF0F2F5), const Color(0xFF3B4A60)),
};

class _AddressCard extends StatelessWidget {
  const _AddressCard({
    required this.address,
    required this.selectionMode,
    required this.disabled,
    required this.onSelect,
    required this.onEdit,
    required this.onDelete,
    required this.onDefault,
  });

  final ClientAddress address;
  final bool selectionMode;
  final bool disabled;
  final VoidCallback onSelect;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onDefault;

  @override
  Widget build(BuildContext context) {
    final (typeBg, typeFg) = _typeColors(address.type);
    final phone = address.phone.trim().isEmpty ? '' : formatCiPhoneDisplay(address.phone);
    final line = address.address.trim().isNotEmpty ? address.address.trim() : address.locationSummary;
    final contact = [address.recipientName.trim(), phone].where((part) => part.isNotEmpty).join('  ·  ');
    final highlighted = address.isDefault;

    final details = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: typeBg, borderRadius: BorderRadius.circular(7)),
              child: Text(
                address.typeLabel,
                style: TextStyle(color: typeFg, fontSize: 12.5, fontWeight: FontWeight.w600),
              ),
            ),
            const Spacer(),
            if (address.isDefault)
              Container(
                padding: const EdgeInsets.fromLTRB(8, 4, 11, 4),
                decoration: BoxDecoration(color: OvanieColors.orange, borderRadius: BorderRadius.circular(99)),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.star_rounded, color: Colors.white, size: 16),
                    SizedBox(width: 4),
                    Text(
                      'Par défaut',
                      style: TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (selectionMode)
              Padding(
                padding: const EdgeInsets.only(top: 1, right: 12),
                child: _Radio(selected: address.isDefault),
              )
            else
              const Padding(
                padding: EdgeInsets.only(top: 1, right: 12),
                child: Icon(Icons.location_on_outlined, color: OvanieColors.orange, size: 23),
              ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    address.label,
                    style: const TextStyle(color: _navy, fontSize: 17, fontWeight: FontWeight.w800),
                  ),
                  if (line.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(line, style: const TextStyle(color: Color(0xFF3B4A60), fontSize: 13.5, height: 1.35)),
                  ],
                ],
              ),
            ),
          ],
        ),
        if (contact.isNotEmpty) ...[
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.person_outline_rounded, color: Color(0xFF5E6B80), size: 20),
              const SizedBox(width: 15),
              Expanded(
                child: Text(contact, style: const TextStyle(color: _muted, fontSize: 13.5)),
              ),
            ],
          ),
        ],
      ],
    );

    final actions = <Widget>[
      if (!selectionMode) ...[
        _CardAction(icon: Icons.edit_outlined, label: 'Modifier', color: _navy, onTap: disabled ? null : onEdit),
        _CardAction(
          icon: Icons.delete_outline_rounded,
          label: 'Supprimer',
          color: const Color(0xFFC81E1E),
          onTap: disabled ? null : onDelete,
        ),
      ],
      if (!address.isDefault)
        _CardAction(
          icon: Icons.star_border_rounded,
          label: 'Définir comme principale',
          color: _navy,
          onTap: disabled ? null : onDefault,
        ),
    ];

    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: highlighted ? OvanieColors.orange : _line, width: highlighted ? 1.5 : 1),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: selectionMode ? onSelect : null,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              details,
              if (actions.isEmpty)
                const SizedBox(height: 10)
              else ...[
                const SizedBox(height: 12),
                const Divider(height: 1, color: _line),
                SizedBox(
                  height: 44,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: selectionMode ? Alignment.centerRight : Alignment.centerLeft,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        for (var i = 0; i < actions.length; i++) ...[
                          if (i > 0) Container(width: 1, height: 18, color: _line),
                          actions[i],
                        ],
                      ],
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Radio extends StatelessWidget {
  const _Radio({required this.selected});

  final bool selected;

  @override
  Widget build(BuildContext context) => Container(
    width: 22,
    height: 22,
    padding: const EdgeInsets.all(4),
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: selected ? OvanieColors.orange : const Color(0xFF9AA5B5), width: 1.6),
    ),
    child: selected
        ? const DecoratedBox(
            decoration: BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
          )
        : null,
  );
}

class _CardAction extends StatelessWidget {
  const _CardAction({required this.icon, required this.label, required this.color, this.onTap});

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(width: 7),
            Text(
              label,
              style: TextStyle(color: color, fontSize: 13.5, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}

class _PrecisionTip extends StatelessWidget {
  const _PrecisionTip();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 14, 12),
      decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: const BoxDecoration(color: Color(0xFFE2E6EC), shape: BoxShape.circle),
            child: const Icon(Icons.location_on_rounded, color: Color(0xFF4A586E), size: 22),
          ),
          Container(width: 1, height: 32, margin: const EdgeInsets.symmetric(horizontal: 12), color: _line),
          const Expanded(
            child: Text(
              'Utilisez des adresses précises pour améliorer l’estimation des frais et délais de livraison.',
              style: TextStyle(color: Color(0xFF4A586E), fontSize: 13, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyAddresses extends StatelessWidget {
  const _EmptyAddresses();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.fromLTRB(26, 20, 26, 40),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Image(image: AssetImage('assets/images/checkout/addresses_empty.png'), width: 270),
          SizedBox(height: 18),
          Text(
            'Aucune adresse enregistrée',
            textAlign: TextAlign.center,
            style: TextStyle(color: _navy, fontSize: 21, fontWeight: FontWeight.w800),
          ),
          SizedBox(height: 10),
          Text(
            'Vous n’avez pas encore ajouté d’adresse. Utilisez les actions ci-dessus pour ajouter votre première '
            'adresse.',
            textAlign: TextAlign.center,
            style: TextStyle(color: _muted, fontSize: 14, height: 1.45),
          ),
        ],
      ),
    );
  }
}

class _DeleteDialog extends StatelessWidget {
  const _DeleteDialog({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 24, 20, 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Supprimer cette adresse ?',
              textAlign: TextAlign.center,
              style: TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Text(
              '« $label » sera retirée de vos adresses enregistrées.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: _muted, fontSize: 14.5, height: 1.4),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: FilledButton(
                onPressed: () => Navigator.of(context).pop(true),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFE02424),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Supprimer', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700)),
              ),
            ),
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Annuler', style: TextStyle(color: Color(0xFF0B3C8C), fontSize: 15)),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.cloud_off_rounded, color: _muted, size: 52),
          const SizedBox(height: 14),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: _muted, height: 1.45),
          ),
          const SizedBox(height: 18),
          PaymentButton(label: 'Réessayer', onPressed: onRetry),
        ],
      ),
    );
  }
}
