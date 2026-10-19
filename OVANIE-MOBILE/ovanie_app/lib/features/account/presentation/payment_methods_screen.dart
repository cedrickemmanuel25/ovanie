import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';
import 'add_payment_method_screen.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);
const _pageBackground = Color(0xFFF2F5F9);

class PaymentMethodsScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;

  const PaymentMethodsScreen({
    super.key,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenCart,
    this.onOpenFavorites,
  });

  @override
  State<PaymentMethodsScreen> createState() => _PaymentMethodsScreenState();
}

class _PaymentMethodsScreenState extends State<PaymentMethodsScreen> {
  final ClientAccountRepository _repository = const ClientAccountRepository();
  AccountCapabilities? _capabilities;
  List<SavedPaymentMethod> _methods = const <SavedPaymentMethod>[];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = _capabilities == null;
      _error = null;
    });
    try {
      final caps = await _repository.capabilities();
      final methods = caps.paymentMethods ? await _repository.paymentMethods() : const <SavedPaymentMethod>[];
      if (!mounted) return;
      setState(() {
        _capabilities = caps;
        _methods = methods;
      });
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  bool get _available => _error == null && _capabilities?.paymentMethods == true;

  Future<void> _openAdd({PaymentEntryType type = PaymentEntryType.mobileMoney}) async {
    final caps = _capabilities;
    if (caps == null || !caps.paymentMethods) return;
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) =>
            AddPaymentMethodScreen(initialType: type, operators: caps.paymentOperators, makeDefault: _methods.isEmpty),
      ),
    );
    if (created == true) {
      await _load();
      if (mounted) showOvanieToast(context, 'Moyen de paiement ajouté.');
    }
  }

  Future<void> _setDefault(SavedPaymentMethod method) async {
    try {
      await _repository.setDefaultPaymentMethod(method.id);
      await _load();
      if (mounted) showOvanieToast(context, 'Moyen de paiement par défaut mis à jour.');
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    }
  }

  Future<void> _delete(SavedPaymentMethod method) async {
    final confirmed = await showDialog<bool>(context: context, builder: (_) => const _DeleteDialog());
    if (confirmed != true) return;
    try {
      await _repository.deletePaymentMethod(method.id);
      await _load();
      if (mounted) showOvanieToast(context, 'Moyen de paiement supprimé.');
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    }
  }

  Future<void> _showMenu(SavedPaymentMethod method) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (_) => _MethodMenu(method: method),
    );
    if (action == 'default') await _setDefault(method);
    if (action == 'delete') await _delete(method);
  }

  @override
  Widget build(BuildContext context) {
    final cards = _methods.where((m) => m.type == 'card').toList(growable: false);
    final mobile = _methods.where((m) => m.type != 'card').toList(growable: false);

    return Scaffold(
      backgroundColor: _pageBackground,
      bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
      body: RefreshIndicator(
        color: OvanieColors.orange,
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            const SliverToBoxAdapter(child: _Header()),
            if (_loading)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
              )
            else if (!_available)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(14, 18, 14, 18),
                  child: _UnavailableCard(onRetry: _load),
                ),
              )
            else if (_methods.isEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(14, 18, 14, 18),
                  child: _EmptyCard(onAdd: () => _openAdd()),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(14, 6, 14, 18),
                sliver: SliverList.list(
                  children: [
                    if (cards.isNotEmpty) ...[
                      const _SectionTitle('Cartes bancaires'),
                      for (final card in cards) ...[
                        _BankCard(method: card, onMenu: () => _showMenu(card)),
                        const SizedBox(height: 12),
                      ],
                    ],
                    if (mobile.isNotEmpty) ...[
                      const _SectionTitle('Mobile money'),
                      for (final item in mobile) ...[
                        _MobileMoneyTile(method: item, onMenu: () => _showMenu(item)),
                        const SizedBox(height: 10),
                      ],
                    ],
                    const _SectionTitle('Autres moyens de paiement'),
                    _AddEntryTile(
                      icon: Icons.phone_iphone_rounded,
                      iconColor: OvanieColors.orange,
                      iconBackground: const Color(0xFFFFF0E5),
                      title: 'Mobile Money',
                      subtitle: 'Orange, MTN, Moov, Wave',
                      onTap: () => _openAdd(type: PaymentEntryType.mobileMoney),
                    ),
                    const SizedBox(height: 10),
                    _AddEntryTile(
                      icon: Icons.credit_card_rounded,
                      iconColor: _navy,
                      iconBackground: const Color(0xFFE8F1FF),
                      title: 'Carte bancaire',
                      subtitle: 'Visa, Mastercard',
                      onTap: () => _openAdd(type: PaymentEntryType.card),
                    ),
                    const SizedBox(height: 14),
                    _AddButton(onTap: () => _openAdd()),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header();

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: EdgeInsets.fromLTRB(6, MediaQuery.paddingOf(context).top + 6, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconButton(
                onPressed: () => Navigator.maybePop(context),
                tooltip: 'Retour',
                icon: const Icon(Icons.arrow_back_rounded, color: _navy, size: 26),
              ),
              const SizedBox(width: 6),
              const Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Mes moyens de paiement',
                    style: TextStyle(color: _navy, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.4),
                  ),
                ),
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.only(left: 12, top: 2),
            child: Text(
              'Gérez vos cartes, mobile money et autres moyens de paiement.',
              style: TextStyle(color: _muted, fontSize: 13, height: 1.35),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(2, 18, 2, 10),
    child: Text(
      text,
      style: const TextStyle(color: _navy, fontSize: 18, fontWeight: FontWeight.w800),
    ),
  );
}

/// Carte bancaire enregistrée, présentée comme une vraie carte.
class _BankCard extends StatelessWidget {
  const _BankCard({required this.method, required this.onMenu});

  final SavedPaymentMethod method;
  final VoidCallback onMenu;

  @override
  Widget build(BuildContext context) {
    final brand = method.cardBrandLabel.trim().isEmpty ? 'CARTE' : method.cardBrandLabel.toUpperCase();
    final brandStyle = TextStyle(
      color: Colors.white,
      fontSize: 34,
      fontWeight: FontWeight.w900,
      fontStyle: FontStyle.italic,
      letterSpacing: -1,
      height: 1,
    );
    return AspectRatio(
      aspectRatio: 1.98,
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF0B1F45), Color(0xFF123A7A), Color(0xFF0B2552)],
          ),
          boxShadow: const [BoxShadow(color: Color(0x330A1B3D), blurRadius: 16, offset: Offset(0, 8))],
        ),
        child: Stack(
          children: [
            Positioned(
              right: -60,
              top: -90,
              child: Container(
                width: 280,
                height: 280,
                decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: 0.06)),
              ),
            ),
            Positioned(
              right: 16,
              bottom: 14,
              child: Text(brand, style: brandStyle.copyWith(color: Colors.white.withValues(alpha: 0.22))),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 6, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(brand, style: brandStyle),
                      ),
                      const Spacer(),
                      IconButton(
                        onPressed: onMenu,
                        tooltip: 'Options',
                        icon: const Icon(Icons.more_vert_rounded, color: Colors.white),
                      ),
                    ],
                  ),
                  const Spacer(),
                  Text(
                    '••••  ${method.cardLast4}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 1.5,
                    ),
                  ),
                  if (method.cardExpiryLabel.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Expire le ${method.cardExpiryLabel}',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 13),
                    ),
                  ],
                  const SizedBox(height: 12),
                  if (method.isDefault)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(99)),
                      child: const Text(
                        'Par défaut',
                        style: TextStyle(color: _navy, fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    )
                  else
                    const SizedBox(height: 26),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String _maskPhone(String raw) {
  final digits = raw.replaceAll(RegExp(r'\D'), '');
  final local = digits.length > 10 ? digits.substring(digits.length - 10) : digits;
  if (local.length < 4) return raw;
  return '${local.substring(0, 2)} •• •• ${local.substring(local.length - 2)}';
}

String? _operatorAsset(String operator) => switch (operator.toLowerCase()) {
  'wave' => 'assets/images/operators/wave.png',
  'orange' => 'assets/images/operators/orange.png',
  'mtn' => 'assets/images/operators/mtn.png',
  'moov' => 'assets/images/operators/moov.png',
  _ => null,
};

class _MobileMoneyTile extends StatelessWidget {
  const _MobileMoneyTile({required this.method, required this.onMenu});

  final SavedPaymentMethod method;
  final VoidCallback onMenu;

  @override
  Widget build(BuildContext context) {
    final asset = _operatorAsset(method.operator);
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 4, 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: const [BoxShadow(color: Color(0x0D0A1B3D), blurRadius: 10, offset: Offset(0, 3))],
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: SizedBox(
              width: 50,
              height: 50,
              child: asset == null
                  ? const ColoredBox(
                      color: Color(0xFFF0F2F5),
                      child: Icon(Icons.account_balance_wallet_rounded, color: _navy),
                    )
                  : Image.asset(asset, fit: BoxFit.cover),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      _maskPhone(method.phone),
                      style: const TextStyle(
                        color: _navy,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.5,
                      ),
                    ),
                    if (method.isDefault) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF0E5),
                          borderRadius: BorderRadius.circular(99),
                        ),
                        child: const Text(
                          'Par défaut',
                          style: TextStyle(color: OvanieColors.orange, fontSize: 11.5, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 2),
                Text(method.operatorLabel, style: const TextStyle(color: _muted, fontSize: 12.5)),
              ],
            ),
          ),
          IconButton(
            onPressed: onMenu,
            tooltip: 'Options',
            icon: const Icon(Icons.more_vert_rounded, color: Color(0xFF4A586E)),
          ),
        ],
      ),
    );
  }
}

class _AddEntryTile extends StatelessWidget {
  const _AddEntryTile({
    required this.icon,
    required this.iconColor,
    required this.iconBackground,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final Color iconColor;
  final Color iconBackground;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 10, 10),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 42,
                decoration: BoxDecoration(color: iconBackground, borderRadius: BorderRadius.circular(9)),
                child: Icon(icon, color: iconColor, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    text: title,
                    style: const TextStyle(color: _navy, fontWeight: FontWeight.w700),
                    children: [
                      TextSpan(
                        text: ' – $subtitle',
                        style: const TextStyle(color: _muted, fontWeight: FontWeight.w400),
                      ),
                    ],
                  ),
                  style: const TextStyle(fontSize: 14.5),
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8)),
            ],
          ),
        ),
      ),
    );
  }
}

class _AddButton extends StatelessWidget {
  const _AddButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: FilledButton(
        onPressed: onTap,
        style: FilledButton.styleFrom(
          backgroundColor: OvanieColors.orange,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.add_rounded, size: 24),
            SizedBox(width: 8),
            Text('Ajouter un moyen de paiement', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.onAdd});

  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 20, 14, 14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Image.asset('assets/images/checkout/payment_methods_empty.png', height: 190),
          const SizedBox(height: 16),
          const Text(
            'Aucun moyen de paiement',
            textAlign: TextAlign.center,
            style: TextStyle(color: _navy, fontSize: 21, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          const Text(
            'Enregistrez-en un pour payer plus vite.',
            textAlign: TextAlign.center,
            style: TextStyle(color: _muted, fontSize: 14.5),
          ),
          const SizedBox(height: 18),
          _AddButton(onTap: onAdd),
        ],
      ),
    );
  }
}

class _UnavailableCard extends StatelessWidget {
  const _UnavailableCard({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 18, 14, 14),
      decoration: BoxDecoration(
        color: const Color(0xFFEEF1F5),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 54,
              height: 54,
              decoration: const BoxDecoration(color: Color(0xFFFBDDDD), shape: BoxShape.circle),
              child: const Icon(Icons.warning_amber_rounded, color: Color(0xFFD92D20), size: 30),
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Les moyens de paiement enregistrés ne sont pas disponibles actuellement.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Color(0xFF3B4A60), fontSize: 14.5, height: 1.45),
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 44,
            child: OutlinedButton.icon(
              onPressed: onRetry,
              style: OutlinedButton.styleFrom(
                foregroundColor: _navy,
                backgroundColor: Colors.white,
                side: const BorderSide(color: Color(0xFFD5DCE6)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Réessayer', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

class _MethodMenu extends StatelessWidget {
  const _MethodMenu({required this.method});

  final SavedPaymentMethod method;

  @override
  Widget build(BuildContext context) {
    Widget row({required IconData icon, required String label, required Color color, required String value}) {
      return InkWell(
        onTap: () => Navigator.of(context).pop(value),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 16),
          child: Row(
            children: [
              Icon(icon, color: color, size: 24),
              const SizedBox(width: 18),
              Text(label, style: TextStyle(color: color, fontSize: 16)),
            ],
          ),
        ),
      );
    }

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Spacer(),
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(color: const Color(0xFFD5DBE4), borderRadius: BorderRadius.circular(99)),
                ),
                Expanded(
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: IconButton(
                      onPressed: () => Navigator.of(context).pop(),
                      tooltip: 'Fermer',
                      style: IconButton.styleFrom(backgroundColor: const Color(0xFFF0F2F5)),
                      icon: const Icon(Icons.close_rounded, color: _navy, size: 22),
                    ),
                  ),
                ),
              ],
            ),
            if (!method.isDefault) ...[
              row(
                icon: Icons.star_border_rounded,
                label: 'Définir comme moyen par défaut',
                color: _navy,
                value: 'default',
              ),
              const Divider(height: 1, color: _line),
            ],
            row(
              icon: Icons.delete_outline_rounded,
              label: 'Supprimer',
              color: const Color(0xFFD92D20),
              value: 'delete',
            ),
          ],
        ),
      ),
    );
  }
}

class _DeleteDialog extends StatelessWidget {
  const _DeleteDialog();

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.symmetric(horizontal: 46),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Supprimer ce moyen de paiement ?',
              textAlign: TextAlign.center,
              style: TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800, height: 1.25),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: FilledButton(
                onPressed: () => Navigator.of(context).pop(true),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFE5202B),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Supprimer', style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700)),
              ),
            ),
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Annuler', style: TextStyle(color: Color(0xFF4A586E), fontSize: 15)),
            ),
          ],
        ),
      ),
    );
  }
}
