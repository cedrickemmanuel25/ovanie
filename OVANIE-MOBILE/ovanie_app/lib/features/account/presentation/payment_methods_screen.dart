import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';
import 'add_payment_method_screen.dart';

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
    try {
      final caps = await _repository.capabilities();
      final methods = caps.paymentMethods
          ? await _repository.paymentMethods()
          : const <SavedPaymentMethod>[];
      if (!mounted) return;
      setState(() {
        _capabilities = caps;
        _methods = methods;
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

  Future<void> _openAdd({PaymentEntryType type = PaymentEntryType.mobileMoney}) async {
    final caps = _capabilities;
    if (caps == null || !caps.paymentMethods) return;
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => AddPaymentMethodScreen(
          initialType: type,
          operators: caps.paymentOperators,
          makeDefault: _methods.isEmpty,
        ),
      ),
    );
    if (created == true) await _load();
  }

  Future<void> _setDefault(SavedPaymentMethod method) async {
    try {
      await _repository.setDefaultPaymentMethod(method.id);
      await _load();
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    }
  }

  Future<void> _delete(SavedPaymentMethod method) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer ce moyen de paiement ?'),
        content: Text(method.displayIdentifier),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFFFF3B1A)),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await _repository.deletePaymentMethod(method.id);
      await _load();
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    }
  }

  void _message(String value) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  void _leaveFor(VoidCallback? destination) {
    if (destination == null) return;
    Navigator.of(context).pop();
    destination();
  }

  @override
  Widget build(BuildContext context) {
    final cards = _methods.where((m) => m.type == 'card').toList(growable: false);
    final mobile = _methods
        .where((m) => m.type != 'card' && m.operator != 'wave')
        .toList(growable: false);
    final other = _methods
        .where((m) => m.type != 'card' && m.operator == 'wave')
        .toList(growable: false);

    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: const OvanieBottomNavigation(
        selectedTab: OvanieMainTab.account,
      ),
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          color: OvanieColors.orange,
          onRefresh: _load,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
                  child: _Header(onBack: () => Navigator.maybePop(context)),
                ),
              ),
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(56, 2, 18, 0),
                  child: Text(
                    'Gérez vos cartes, mobile money et autres moyens de paiement.',
                    style: TextStyle(
                      color: Color(0xFF334E88),
                      fontSize: 13.2,
                      height: 1.4,
                    ),
                  ),
                ),
              ),
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(18, 14, 18, 0),
                  child: _InfoBanner(),
                ),
              ),
              if (_loading)
                const SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: CircularProgressIndicator(color: OvanieColors.orange),
                  ),
                )
              else if (_error != null)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: _ErrorState(message: _error!, onRetry: _load),
                )
              else if (_capabilities?.paymentMethods != true)
                const SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: Padding(
                      padding: EdgeInsets.all(30),
                      child: Text(
                        'Les moyens de paiement enregistrés ne sont pas disponibles actuellement.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Color(0xFF6E7892), fontSize: 12.3),
                      ),
                    ),
                  ),
                )
              else if (_methods.isEmpty)
                SliverToBoxAdapter(
                  child: _EmptyPaymentState(onAdd: _openAdd),
                )
              else ...[
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(18, 16, 18, 0),
                    child: _AddButton(onTap: () => _openAdd()),
                  ),
                ),
                if (cards.isNotEmpty)
                  SliverToBoxAdapter(
                    child: _PaymentSection(
                      title: 'Cartes bancaires',
                      methods: cards,
                      onMenu: _showMenu,
                    ),
                  ),
                if (mobile.isNotEmpty)
                  SliverToBoxAdapter(
                    child: _PaymentSection(
                      title: 'Mobile money',
                      methods: mobile,
                      onMenu: _showMenu,
                    ),
                  ),
                if (other.isNotEmpty)
                  SliverToBoxAdapter(
                    child: _PaymentSection(
                      title: 'Autres moyens de paiement',
                      methods: other,
                      onMenu: _showMenu,
                    ),
                  ),
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(18, 20, 18, 18),
                    child: _SecurityBanner(),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showMenu(SavedPaymentMethod method) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!method.isDefault)
                ListTile(
                  leading: const Icon(Icons.star_border_rounded, color: Color(0xFF0A2B77)),
                  title: const Text('Définir comme moyen par défaut'),
                  onTap: () => Navigator.pop(context, 'default'),
                ),
              ListTile(
                leading: const Icon(Icons.delete_outline_rounded, color: Color(0xFFFF3B30)),
                title: const Text('Supprimer', style: TextStyle(color: Color(0xFFFF3B30))),
                onTap: () => Navigator.pop(context, 'delete'),
              ),
            ],
          ),
        ),
      ),
    );
    if (action == 'default') await _setDefault(method);
    if (action == 'delete') await _delete(method);
  }
}

class _Header extends StatelessWidget {
  final VoidCallback onBack;
  const _Header({required this.onBack});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        IconButton(
          onPressed: onBack,
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF061B55), size: 22),
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints.tightFor(width: 42, height: 42),
        ),
        const SizedBox(width: 7),
        const Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              'Mes moyens de paiement',
              maxLines: 1,
              style: TextStyle(
                color: Color(0xFF081D5A),
                fontSize: 24,
                height: 1.05,
                fontWeight: FontWeight.w800,
                letterSpacing: -.5,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF7FAFF),
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFD3E1FA)),
      ),
      child: const Row(
        children: [
          Icon(Icons.info_outline_rounded, color: Color(0xFF0A67FF), size: 22),
          SizedBox(width: 12),
          Expanded(
            child: Text(
              'Le moyen de paiement par défaut sera sélectionné automatiquement\nlors de vos prochains achats.',
              style: TextStyle(
                color: Color(0xFF0B245F),
                fontSize: 12.2,
                height: 1.55,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AddButton extends StatelessWidget {
  final VoidCallback onTap;
  const _AddButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 50,
      child: FilledButton(
        onPressed: onTap,
        style: FilledButton.styleFrom(
          backgroundColor: const Color(0xFFFF3D0A),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.add_rounded, size: 24),
            SizedBox(width: 14),
            Text(
              'Ajouter un moyen de paiement',
              style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}

class _PaymentSection extends StatelessWidget {
  final String title;
  final List<SavedPaymentMethod> methods;
  final ValueChanged<SavedPaymentMethod> onMenu;

  const _PaymentSection({
    required this.title,
    required this.methods,
    required this.onMenu,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 22, 18, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: Color(0xFF081D5A),
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 9),
          ...methods.map(
            (method) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _PaymentMethodCard(method: method, onMenu: () => onMenu(method)),
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentMethodCard extends StatelessWidget {
  final SavedPaymentMethod method;
  final VoidCallback onMenu;

  const _PaymentMethodCard({required this.method, required this.onMenu});

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 82),
      padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFE0E5EF)),
        boxShadow: const [
          BoxShadow(color: Color(0x0C071847), blurRadius: 15, offset: Offset(0, 5)),
        ],
      ),
      child: Row(
        children: [
          SizedBox(width: 64, child: _PaymentBrand(method: method)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        method.type == 'card'
                            ? '${method.cardBrandLabel}  ••••  ${method.cardLast4}'
                            : method.operatorLabel,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF081D5A),
                          fontSize: 14.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (method.isDefault) ...[
                      const SizedBox(width: 7),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEAF1FF),
                          borderRadius: BorderRadius.circular(7),
                        ),
                        child: const Text(
                          'Par défaut',
                          style: TextStyle(
                            color: Color(0xFF1A48AE),
                            fontSize: 10.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 4),
                if (method.type == 'card')
                  Text(
                    method.cardExpiryLabel.isEmpty
                        ? method.accountName.toUpperCase()
                        : 'Expire le ${method.cardExpiryLabel}',
                    style: const TextStyle(color: Color(0xFF43588C), fontSize: 12.3),
                  )
                else
                  Text(
                    method.phone,
                    style: const TextStyle(color: Color(0xFF43588C), fontSize: 12.3),
                  ),
                const SizedBox(height: 3),
                Text(
                  method.accountName.toUpperCase(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Color(0xFF43588C), fontSize: 12.3),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onMenu,
            icon: const Icon(Icons.more_vert_rounded, color: Color(0xFF071B54), size: 22),
          ),
          const Icon(Icons.chevron_right_rounded, color: Color(0xFF071B54), size: 24),
        ],
      ),
    );
  }
}

class _PaymentBrand extends StatelessWidget {
  final SavedPaymentMethod method;
  const _PaymentBrand({required this.method});

  @override
  Widget build(BuildContext context) {
    if (method.type == 'card') {
      final visa = method.cardBrand.toLowerCase() == 'visa';
      return Container(
        height: 46,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFFDDE3ED)),
        ),
        child: visa
            ? const Text(
                'VISA',
                style: TextStyle(
                  color: Color(0xFF1345B8),
                  fontSize: 21,
                  fontStyle: FontStyle.italic,
                  fontWeight: FontWeight.w900,
                ),
              )
            : const _MastercardMark(),
      );
    }

    final asset = _operatorAsset(method.operator);
    return Container(
      height: 52,
      width: 60,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(11),
      ),
      child: asset == null
          ? const Icon(Icons.account_balance_wallet_rounded, color: Color(0xFF0B2C76), size: 30)
          : Image.asset(asset, fit: BoxFit.contain),
    );
  }
}

class _MastercardMark extends StatelessWidget {
  const _MastercardMark();
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 42,
      height: 26,
      child: Stack(
        children: [
          Positioned(left: 5, top: 2, child: _circle(const Color(0xFFE60019))),
          Positioned(right: 5, top: 2, child: _circle(const Color(0xFFFFA500))),
        ],
      ),
    );
  }

  Widget _circle(Color color) => Container(
        width: 23,
        height: 23,
        decoration: BoxDecoration(shape: BoxShape.circle, color: color.withValues(alpha: .93)),
      );
}

String? _operatorAsset(String operator) {
  switch (operator.toLowerCase()) {
    case 'wave':
      return 'assets/images/operators/wave.png';
    case 'orange':
      return 'assets/images/operators/orange.png';
    case 'mtn':
      return 'assets/images/operators/mtn.png';
    case 'moov':
      return 'assets/images/operators/moov.png';
    default:
      return null;
  }
}

class _EmptyPaymentState extends StatelessWidget {
  final void Function({PaymentEntryType type}) onAdd;
  const _EmptyPaymentState({required this.onAdd});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
      child: Column(
        children: [
          Image.asset(
            'assets/images/payment_empty_illustration.png',
            width: 245,
            fit: BoxFit.contain,
          ),
          const SizedBox(height: 10),
          const Text(
            'Aucun moyen de paiement enregistré',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Color(0xFF081D5A),
              fontSize: 21,
              height: 1.12,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          const Text(
            'Vous n’avez pas encore ajouté de moyen de paiement.\nAjoutez-en un pour effectuer vos achats\nrapidement et en toute sécurité.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Color(0xFF43588C),
              fontSize: 13.5,
              height: 1.55,
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: 360,
            height: 52,
            child: FilledButton(
              onPressed: () => onAdd(type: PaymentEntryType.mobileMoney),
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFFF3D0A),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.add_rounded, size: 25),
                  SizedBox(width: 18),
                  Flexible(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        'Ajouter un moyen de paiement',
                        maxLines: 1,
                        style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          const _SecurityBanner(),
        ],
      ),
    );
  }
}

class _SecurityBanner extends StatelessWidget {
  const _SecurityBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7F2),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            alignment: Alignment.center,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.shield_outlined, color: Color(0xFFFF3D0A), size: 34),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Paiements 100% sécurisés',
                  style: TextStyle(
                    color: Color(0xFFFF3D0A),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 7),
                Text(
                  'Vos informations sont protégées et sécurisées avec les meilleures normes de sécurité.',
                  style: TextStyle(
                    color: Color(0xFF0B245F),
                    fontSize: 12.2,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          const Icon(Icons.lock_outline_rounded, color: Color(0xFFFF3D0A), size: 34),
        ],
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(30),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 9),
            FilledButton(onPressed: () => onRetry(), child: const Text('Réessayer')),
          ],
        ),
      ),
    );
  }
}
