import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/payments_repository.dart';
import '../domain/payment_models.dart';

enum _PaymentFilter { all, success, pending, failed, refunded }

class PaymentHistoryScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;

  const PaymentHistoryScreen({
    super.key,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenCart,
    this.onOpenFavorites,
  });

  @override
  State<PaymentHistoryScreen> createState() => _PaymentHistoryScreenState();
}

class _PaymentHistoryScreenState extends State<PaymentHistoryScreen> {
  static const _navy = Color(0xFF071B53);
  static const _muted = Color(0xFF52658F);
  static const _line = Color(0xFFE1E7F0);
  static const _infoBg = Color(0xFFF7FAFF);
  static const _green = Color(0xFF14A650);
  static const _orange = Color(0xFFFF4B0A);
  static const _blue = Color(0xFF1464EB);
  static const _red = Color(0xFFE62E2E);

  final _repository = const PaymentsRepository();
  ClientPaymentHistoryPage? _page;
  bool _loading = true;
  String? _error;
  _PaymentFilter _filter = _PaymentFilter.all;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final page = await _repository.history(perPage: 100);
      if (!mounted) return;
      setState(() {
        _page = page;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  void _goRoot(VoidCallback? callback) {
    if (callback == null) return;
    Navigator.of(context).pop();
    callback();
  }

  void _openOrders() {
    if (widget.onOpenHome != null || widget.onOpenCategories != null || widget.onOpenCart != null || widget.onOpenFavorites != null) {
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => OrdersScreen(
            onOpenCart: widget.onOpenCart,
            onStartShopping: widget.onOpenCategories,
          ),
        ),
      );
      return;
    }
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const OrdersScreen()));
  }

  void _discoverProducts() {
    if (widget.onOpenCategories != null) {
      _goRoot(widget.onOpenCategories);
      return;
    }
    Navigator.maybePop(context);
  }

  void _openSupport() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()));
  }

  List<ClientPaymentHistoryItem> get _allItems => _page?.items ?? const <ClientPaymentHistoryItem>[];

  bool _isSuccess(ClientPaymentHistoryItem item) {
    final s = item.status.toLowerCase();
    return s == 'paid' || s == 'commission_paid' || s == 'escrow_held' || s == 'success' || s == 'successful';
  }

  bool _isPending(ClientPaymentHistoryItem item) {
    final s = item.status.toLowerCase();
    return s == 'pending' || s == 'initiated' || s == 'processing' || s == 'waiting';
  }

  bool _isFailed(ClientPaymentHistoryItem item) {
    final s = item.status.toLowerCase();
    return s == 'failed' || s == 'cancelled' || s == 'canceled' || s == 'declined';
  }

  bool _isRefunded(ClientPaymentHistoryItem item) => item.status.toLowerCase() == 'refunded';

  List<ClientPaymentHistoryItem> get _filteredItems {
    return _allItems.where((item) {
      return switch (_filter) {
        _PaymentFilter.all => true,
        _PaymentFilter.success => _isSuccess(item),
        _PaymentFilter.pending => _isPending(item),
        _PaymentFilter.failed => _isFailed(item),
        _PaymentFilter.refunded => _isRefunded(item),
      };
    }).toList(growable: false);
  }

  int get _successCount => _allItems.where(_isSuccess).length;
  int get _pendingCount => _allItems.where(_isPending).length;

  String _date(DateTime? value) {
    if (value == null) return 'Date indisponible';
    const months = <String>[
      'janvier',
      'février',
      'mars',
      'avril',
      'mai',
      'juin',
      'juillet',
      'août',
      'septembre',
      'octobre',
      'novembre',
      'décembre',
    ];
    String two(int n) => n.toString().padLeft(2, '0');
    return '${value.day} ${months[value.month - 1]} ${value.year} à ${two(value.hour)}:${two(value.minute)}';
  }

  String _statusLabel(ClientPaymentHistoryItem item) {
    if (_isSuccess(item)) return 'Réussi';
    if (_isPending(item)) return 'En attente';
    if (_isFailed(item)) return 'Échoué';
    if (_isRefunded(item)) return 'Remboursé';
    return item.statusLabel;
  }

  Color _statusColor(ClientPaymentHistoryItem item) {
    if (_isSuccess(item)) return _green;
    if (_isPending(item)) return const Color(0xFFF08A21);
    if (_isFailed(item)) return _red;
    if (_isRefunded(item)) return _blue;
    return const Color(0xFF7785A6);
  }

  Color _statusBackground(ClientPaymentHistoryItem item) {
    if (_isSuccess(item)) return const Color(0xFFEAF8EE);
    if (_isPending(item)) return const Color(0xFFFFF1E5);
    if (_isFailed(item)) return const Color(0xFFFFECEC);
    if (_isRefunded(item)) return const Color(0xFFEAF2FF);
    return const Color(0xFFF1F3F7);
  }

  String _actionLabel(ClientPaymentHistoryItem item) {
    if (_isSuccess(item)) return 'Voir le reçu';
    if (_isPending(item)) return 'Voir le détail';
    if (_isFailed(item)) return 'Réessayer';
    if (_isRefunded(item)) return 'Voir le détail';
    return 'Voir le détail';
  }

  void _openPayment(ClientPaymentHistoryItem item) {
    if (item.orderId > 0) {
      Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: item.orderId)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: CartStore.instance,
      builder: (context, _) {
        final items = _filteredItems;
        final hasAny = _allItems.isNotEmpty;
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
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 24),
                children: [
                  _buildHeader(),
                  const SizedBox(height: 18),
                  _buildInfoBanner(),
                  const SizedBox(height: 16),
                  _buildFilters(),
                  if (_loading && _page == null) ...[
                    const SizedBox(height: 120),
                    const Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                    const SizedBox(height: 160),
                  ] else if (_error != null) ...[
                    const SizedBox(height: 34),
                    _buildError(),
                    const SizedBox(height: 80),
                  ] else if (!hasAny || items.isEmpty) ...[
                    _buildEmptyState(filteredEmpty: hasAny),
                  ] else ...[
                    const SizedBox(height: 16),
                    _buildStats(),
                    const SizedBox(height: 14),
                    ...items.map(_buildPaymentCard),
                  ],
                  const SizedBox(height: 8),
                  _buildHelpBanner(),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildHeader() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        IconButton(
          onPressed: () => Navigator.maybePop(context),
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints.tightFor(width: 42, height: 42),
          icon: const Icon(Icons.arrow_back_rounded, color: _navy, size: 29),
        ),
        const SizedBox(width: 8),
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Historique des paiements',
                style: TextStyle(color: _navy, fontSize: 25.5, height: 1.06, fontWeight: FontWeight.w900, letterSpacing: -0.45),
              ),
              SizedBox(height: 7),
              Text('Consultez tous vos paiements et reçus.', style: TextStyle(color: _muted, fontSize: 13.1, height: 1.35)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildInfoBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      decoration: BoxDecoration(
        color: _infoBg,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFCFE0FF)),
      ),
      child: const Row(
        children: [
          Icon(Icons.info_outline_rounded, color: _blue, size: 25),
          SizedBox(width: 13),
          Expanded(
            child: Text(
              'Retrouvez ici vos transactions récentes, leurs statuts et vos reçus.',
              style: TextStyle(color: _navy, fontSize: 12.6, height: 1.35, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilters() {
    const entries = <(_PaymentFilter, String)>[
      (_PaymentFilter.all, 'Tous'),
      (_PaymentFilter.success, 'Réussis'),
      (_PaymentFilter.pending, 'En attente'),
      (_PaymentFilter.failed, 'Échoués'),
      (_PaymentFilter.refunded, 'Remboursés'),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: [
          for (var i = 0; i < entries.length; i++) ...[
            _PaymentFilterChip(
              label: entries[i].$2,
              selected: _filter == entries[i].$1,
              onTap: () => setState(() => _filter = entries[i].$1),
            ),
            if (i < entries.length - 1) const SizedBox(width: 10),
          ],
        ],
      ),
    );
  }

  Widget _buildStats() {
    return Row(
      children: [
        Expanded(
          child: _PaymentStatCard(
            icon: Icons.account_balance_wallet_outlined,
            iconColor: _blue,
            iconBackground: const Color(0xFFEEF4FF),
            value: '${_page?.total ?? _allItems.length}',
            label: 'Paiements',
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _PaymentStatCard(
            icon: Icons.check_circle_outline_rounded,
            iconColor: _green,
            iconBackground: const Color(0xFFEAF8EE),
            value: '$_successCount',
            label: 'Réussis',
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _PaymentStatCard(
            icon: Icons.schedule_rounded,
            iconColor: const Color(0xFFF07818),
            iconBackground: const Color(0xFFFFF2E8),
            value: '$_pendingCount',
            label: 'En attente',
          ),
        ),
      ],
    );
  }

  Widget _buildPaymentCard(ClientPaymentHistoryItem item) {
    final method = item.methodLabel;
    final statusColor = _statusColor(item);
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(12, 12, 10, 11),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _line),
        boxShadow: const [
          BoxShadow(color: Color(0x08071B53), blurRadius: 12, offset: Offset(0, 4)),
        ],
      ),
      child: Row(
        children: [
          _PaymentMethodVisual(method: method, refunded: _isRefunded(item)),
          const SizedBox(width: 12),
          Expanded(
            flex: 12,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(method, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _navy, fontSize: 13.6, fontWeight: FontWeight.w900)),
                const SizedBox(height: 5),
                Text(
                  _isRefunded(item)
                      ? 'Retour ${item.reference.isNotEmpty ? item.reference : item.orderNumber}'
                      : 'Commande ${item.orderNumber.isNotEmpty ? item.orderNumber : item.reference}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: _muted, fontSize: 11.1, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 5),
                Text(_date(item.paidAt ?? item.createdAt), style: const TextStyle(color: _muted, fontSize: 10.9)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            flex: 10,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(formatFcfa(item.amount), maxLines: 1, style: const TextStyle(color: _orange, fontSize: 15.8, fontWeight: FontWeight.w900)),
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: _statusBackground(item), borderRadius: BorderRadius.circular(20)),
                  child: Text(_statusLabel(item), style: TextStyle(color: statusColor, fontSize: 9.5, fontWeight: FontWeight.w900)),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  height: 31,
                  child: OutlinedButton(
                    onPressed: item.orderId > 0 ? () => _openPayment(item) : null,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _navy,
                      padding: const EdgeInsets.symmetric(horizontal: 13),
                      side: const BorderSide(color: _navy, width: 1),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                    ),
                    child: Text(_actionLabel(item), maxLines: 1, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800)),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 2),
          const Icon(Icons.chevron_right_rounded, color: _navy, size: 24),
        ],
      ),
    );
  }

  Widget _buildEmptyState({required bool filteredEmpty}) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(0, 42, 0, 34),
      child: Column(
        children: [
          Image.asset(
            'assets/images/payment_history_empty_illustration.png',
            width: 300,
            height: 260,
            fit: BoxFit.contain,
            errorBuilder: (_, __, ___) => const SizedBox(height: 220, child: Center(child: Icon(Icons.account_balance_wallet_outlined, size: 120, color: Color(0xFFBBD0FA)))),
          ),
          const SizedBox(height: 12),
          Text(
            filteredEmpty ? 'Aucun paiement dans ce filtre' : 'Aucun paiement pour le moment',
            textAlign: TextAlign.center,
            style: const TextStyle(color: _navy, fontSize: 22.5, height: 1.15, fontWeight: FontWeight.w900, letterSpacing: -0.25),
          ),
          const SizedBox(height: 14),
          Text(
            filteredEmpty
                ? 'Aucune transaction ne correspond au statut sélectionné.'
                : 'Vos paiements, reçus et remboursements\napparaîtront ici après vos premières commandes.',
            textAlign: TextAlign.center,
            style: const TextStyle(color: _muted, fontSize: 13.7, height: 1.6, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 27),
          if (!filteredEmpty)
            Row(
              children: [
                Expanded(
                  child: SizedBox(
                    height: 49,
                    child: FilledButton(
                      onPressed: _discoverProducts,
                      style: FilledButton.styleFrom(
                        backgroundColor: _orange,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text('Découvrir nos produits', maxLines: 1, style: TextStyle(fontSize: 13.3, fontWeight: FontWeight.w900)),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: SizedBox(
                    height: 49,
                    child: OutlinedButton(
                      onPressed: _openOrders,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _navy,
                        side: const BorderSide(color: _navy, width: 1.2),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text('Voir mes commandes', maxLines: 1, style: TextStyle(fontSize: 13.3, fontWeight: FontWeight.w900)),
                      ),
                    ),
                  ),
                ),
              ],
            )
          else
            TextButton(
              onPressed: () => setState(() => _filter = _PaymentFilter.all),
              child: const Text('Afficher tous les paiements', style: TextStyle(color: _orange, fontWeight: FontWeight.w800)),
            ),
        ],
      ),
    );
  }

  Widget _buildError() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(color: const Color(0xFFFFF6F2), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFFD8C8))),
      child: Column(
        children: [
          const Icon(Icons.cloud_off_rounded, color: _orange, size: 40),
          const SizedBox(height: 10),
          Text(_error ?? 'Impossible de charger vos paiements.', textAlign: TextAlign.center, style: const TextStyle(color: _navy, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: _load, child: const Text('Réessayer')),
        ],
      ),
    );
  }

  Widget _buildHelpBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FBFF),
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFD5E3F9)),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.help_outline_rounded, color: _blue, size: 27),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Besoin d’aide sur un paiement ?', style: TextStyle(color: _navy, fontSize: 12.4, fontWeight: FontWeight.w900)),
                SizedBox(height: 4),
                Text('Notre équipe peut vous accompagner.', style: TextStyle(color: _muted, fontSize: 11.1)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          SizedBox(
            height: 42,
            child: FilledButton(
              onPressed: _openSupport,
              style: FilledButton.styleFrom(
                backgroundColor: _orange,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 18),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text('Nous contacter', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w900)),
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentFilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _PaymentFilterChip({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 43,
      child: OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          backgroundColor: selected ? const Color(0xFFFF4B0A) : Colors.white,
          foregroundColor: selected ? Colors.white : const Color(0xFF071B53),
          side: BorderSide(color: selected ? const Color(0xFFFF4B0A) : const Color(0xFFD8DFEA)),
          padding: const EdgeInsets.symmetric(horizontal: 22),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: Text(label, style: TextStyle(fontSize: 12.2, fontWeight: selected ? FontWeight.w900 : FontWeight.w700)),
      ),
    );
  }
}

class _PaymentStatCard extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final Color iconBackground;
  final String value;
  final String label;

  const _PaymentStatCard({
    required this.icon,
    required this.iconColor,
    required this.iconBackground,
    required this.value,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 88,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE1E7F0)),
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: iconBackground, borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, color: iconColor, size: 27),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(value, style: const TextStyle(color: Color(0xFF071B53), fontSize: 18, fontWeight: FontWeight.w900)),
                const SizedBox(height: 2),
                Text(label, maxLines: 1, style: const TextStyle(color: Color(0xFF52658F), fontSize: 10.8, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentMethodVisual extends StatelessWidget {
  final String method;
  final bool refunded;

  const _PaymentMethodVisual({required this.method, required this.refunded});

  @override
  Widget build(BuildContext context) {
    if (refunded) {
      return Container(
        width: 64,
        height: 64,
        decoration: BoxDecoration(color: const Color(0xFFEAF2FF), borderRadius: BorderRadius.circular(9)),
        child: const Icon(Icons.history_rounded, color: Color(0xFF1464EB), size: 36),
      );
    }

    final lower = method.toLowerCase();
    String? asset;
    if (lower.contains('wave')) asset = 'assets/images/operators/wave.png';
    if (lower.contains('orange')) asset = 'assets/images/operators/orange.png';
    if (lower.contains('mtn')) asset = 'assets/images/operators/mtn.png';
    if (lower.contains('moov')) asset = 'assets/images/operators/moov.png';

    if (asset != null) {
      return Container(
        width: 64,
        height: 64,
        padding: const EdgeInsets.all(7),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(9), border: Border.all(color: const Color(0xFFE7EAF0))),
        child: Image.asset(asset, fit: BoxFit.contain),
      );
    }

    if (lower.contains('carte') || lower.contains('card')) {
      return Container(
        width: 64,
        height: 64,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(9), border: Border.all(color: const Color(0xFFE7EAF0))),
        child: const Text('VISA', style: TextStyle(color: Color(0xFF1346B8), fontWeight: FontWeight.w900, fontStyle: FontStyle.italic, fontSize: 19)),
      );
    }

    if (lower.contains('livraison') || lower.contains('cash')) {
      return Container(
        width: 64,
        height: 64,
        decoration: BoxDecoration(color: const Color(0xFFFFF2E8), borderRadius: BorderRadius.circular(9)),
        child: const Icon(Icons.inventory_2_outlined, color: Color(0xFFFF4B0A), size: 34),
      );
    }

    return Container(
      width: 64,
      height: 64,
      decoration: BoxDecoration(color: const Color(0xFFEEF4FF), borderRadius: BorderRadius.circular(9)),
      child: const Icon(Icons.account_balance_wallet_outlined, color: Color(0xFF1464EB), size: 34),
    );
  }
}
