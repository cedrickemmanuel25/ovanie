import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../cart/domain/cart_store.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/payments_repository.dart';
import '../domain/payment_models.dart';
import 'payment_receipt_screen.dart';
import 'payment_ui.dart';

enum _PaymentFilter { all, success, pending, failed, refunded }

enum _State { success, pending, failed, refunded, other }

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);

_State _stateOf(ClientPaymentHistoryItem item) {
  final s = item.status.toLowerCase();
  if (s == 'refunded') return _State.refunded;
  if (const {'paid', 'commission_paid', 'escrow_held', 'success', 'successful'}.contains(s)) return _State.success;
  if (const {'pending', 'initiated', 'processing', 'waiting'}.contains(s)) return _State.pending;
  if (const {'failed', 'cancelled', 'canceled', 'declined'}.contains(s)) return _State.failed;
  return _State.other;
}

String _statusLabel(ClientPaymentHistoryItem item) => switch (_stateOf(item)) {
  _State.success => 'Réussi',
  _State.pending => 'En attente',
  _State.failed => 'Échoué',
  _State.refunded => 'Remboursé',
  _State.other => item.statusLabel,
};

(Color, Color) _statusColors(ClientPaymentHistoryItem item) => switch (_stateOf(item)) {
  _State.success => (const Color(0xFF138A4B), const Color(0xFFE6F6EC)),
  _State.pending => (const Color(0xFFC2620A), const Color(0xFFFFF0E0)),
  _State.failed => (const Color(0xFFD92D20), const Color(0xFFFDE8E8)),
  _State.refunded => (const Color(0xFF1D4FA8), const Color(0xFFE6F0FF)),
  _State.other => (const Color(0xFF5E6B80), const Color(0xFFF0F2F5)),
};

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
    setState(() {
      _loading = _page == null;
      _error = null;
    });
    try {
      final page = await _repository.history(perPage: 100);
      if (mounted) setState(() => _page = page);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<ClientPaymentHistoryItem> get _allItems => _page?.items ?? const <ClientPaymentHistoryItem>[];

  List<ClientPaymentHistoryItem> get _filteredItems {
    final wanted = switch (_filter) {
      _PaymentFilter.all => null,
      _PaymentFilter.success => _State.success,
      _PaymentFilter.pending => _State.pending,
      _PaymentFilter.failed => _State.failed,
      _PaymentFilter.refunded => _State.refunded,
    };
    if (wanted == null) return _allItems;
    return _allItems.where((item) => _stateOf(item) == wanted).toList(growable: false);
  }

  void _openSupport() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SupportCenterScreen(
          onOpenHome: widget.onOpenHome,
          onOpenCategories: widget.onOpenCategories,
          onOpenCart: widget.onOpenCart,
          onOpenFavorites: widget.onOpenFavorites,
        ),
      ),
    );
  }

  void _openOrders() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrdersScreen(onOpenCart: widget.onOpenCart, onStartShopping: widget.onOpenCategories),
      ),
    );
  }

  /// Reçu pour un paiement abouti ou remboursé ; sinon le détail de la
  /// commande, d'où le client peut reprendre ou suivre son paiement.
  void _openPayment(ClientPaymentHistoryItem item) {
    final state = _stateOf(item);
    if ((state == _State.pending || state == _State.failed) && item.orderId > 0) {
      Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: item.orderId)));
      return;
    }
    final (color, _) = _statusColors(item);
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => PaymentReceiptScreen(
          payment: item,
          statusLabel: _statusLabel(item),
          statusColor: color,
          success: state == _State.success,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: CartStore.instance,
      builder: (context, _) {
        final items = _filteredItems;
        final all = _allItems;
        return Scaffold(
          backgroundColor: Colors.white,
          bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
          body: SafeArea(
            bottom: false,
            child: RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: _load,
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverToBoxAdapter(child: _buildHeader()),
                  if (_loading)
                    const SliverFillRemaining(
                      hasScrollBody: false,
                      child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                    )
                  else if (_page == null)
                    SliverFillRemaining(
                      hasScrollBody: false,
                      child: _ErrorState(message: _error ?? 'Historique indisponible.', onRetry: _load),
                    )
                  else ...[
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),
                        child: _Stats(
                          total: _page?.total ?? all.length,
                          success: all.where((i) => _stateOf(i) == _State.success).length,
                          pending: all.where((i) => _stateOf(i) == _State.pending).length,
                        ),
                      ),
                    ),
                    SliverToBoxAdapter(child: _buildFilters()),
                    if (items.isEmpty)
                      SliverFillRemaining(
                        hasScrollBody: false,
                        child: _EmptyState(
                          filtered: all.isNotEmpty,
                          onShowAll: () => setState(() => _filter = _PaymentFilter.all),
                          onOrders: _openOrders,
                          onSupport: _openSupport,
                        ),
                      )
                    else ...[
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(12, 2, 12, 0),
                        sliver: SliverList.separated(
                          itemCount: items.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 8),
                          itemBuilder: (context, index) =>
                              _PaymentCard(item: items[index], onTap: () => _openPayment(items[index])),
                        ),
                      ),
                      SliverToBoxAdapter(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(12, 12, 12, 18),
                          child: _HelpCard(onContact: _openSupport),
                        ),
                      ),
                    ],
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 6, 14, 0),
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
                    'Historique des paiements',
                    style: TextStyle(color: _navy, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.4),
                  ),
                ),
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.only(left: 14, top: 2),
            child: Text(
              'Retrouvez ici vos transactions récentes, leurs statuts et vos reçus.',
              style: TextStyle(color: _muted, fontSize: 13, height: 1.35),
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
    return SizedBox(
      height: 62,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
        itemCount: entries.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final (filter, label) = entries[index];
          final selected = filter == _filter;
          return Material(
            color: selected ? OvanieColors.navy : Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(99),
              side: BorderSide(color: selected ? OvanieColors.navy : const Color(0xFFD5DCE6)),
            ),
            child: InkWell(
              onTap: () => setState(() => _filter = filter),
              customBorder: const StadiumBorder(),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Center(
                  child: Text(
                    label,
                    style: TextStyle(
                      color: selected ? Colors.white : _navy,
                      fontSize: 13.5,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _Stats extends StatelessWidget {
  const _Stats({required this.total, required this.success, required this.pending});

  final int total;
  final int success;
  final int pending;

  @override
  Widget build(BuildContext context) {
    Widget cell(int value, String label) => Expanded(
      child: Column(
        children: [
          Text(
            '$value',
            style: const TextStyle(color: _navy, fontSize: 21, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(color: _muted, fontSize: 13)),
        ],
      ),
    );
    Widget divider() => Container(width: 1, height: 34, color: const Color(0xFFD9DFE7));
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          cell(total, 'Paiements'),
          divider(),
          cell(success, 'Réussis'),
          divider(),
          cell(pending, 'En attente'),
        ],
      ),
    );
  }
}

class _PaymentCard extends StatelessWidget {
  const _PaymentCard({required this.item, required this.onTap});

  final ClientPaymentHistoryItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final state = _stateOf(item);
    final (fg, bg) = _statusColors(item);
    final refund = state == _State.refunded || item.type.toLowerCase().contains('refund');
    final number = item.orderNumber.isNotEmpty ? item.orderNumber : item.reference;
    final title = refund ? 'Retour ${item.reference.isNotEmpty ? item.reference : number}' : 'Commande $number';
    final action = state == _State.success || state == _State.refunded ? 'Voir le reçu' : 'Voir le détail';

    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: _line),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(10, 10, 4, 10),
          child: Row(
            children: [
              _MethodVisual(item: item, refund: refund),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: _navy, fontSize: 14.5, fontWeight: FontWeight.w700),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          formatFcfa(item.amount),
                          style: const TextStyle(color: _navy, fontSize: 14.5, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            paymentDateTime(item.paidAt ?? item.createdAt),
                            style: const TextStyle(color: _muted, fontSize: 12.5),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                          decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
                          child: Text(
                            _statusLabel(item),
                            style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        Text(
                          action,
                          style: const TextStyle(color: OvanieColors.orange, fontSize: 13, fontWeight: FontWeight.w500),
                        ),
                        const Icon(Icons.chevron_right_rounded, color: OvanieColors.orange, size: 18),
                      ],
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8), size: 24),
            ],
          ),
        ),
      ),
    );
  }
}

class _MethodVisual extends StatelessWidget {
  const _MethodVisual({required this.item, required this.refund});

  final ClientPaymentHistoryItem item;
  final bool refund;

  @override
  Widget build(BuildContext context) {
    Widget box(Color color, Widget child) => Container(
      width: 52,
      height: 52,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(10)),
      child: child,
    );
    if (refund) return box(const Color(0xFFE6F0FF), const Icon(Icons.undo_rounded, color: Color(0xFF1D4FA8), size: 28));
    final label = item.methodLabel.toLowerCase();
    final asset = switch (label) {
      final l when l.contains('wave') => 'assets/images/operators/wave.png',
      final l when l.contains('orange') => 'assets/images/operators/orange.png',
      final l when l.contains('mtn') => 'assets/images/operators/mtn.png',
      final l when l.contains('moov') => 'assets/images/operators/moov.png',
      _ => null,
    };
    if (asset != null) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Image.asset(asset, width: 52, height: 52, fit: BoxFit.cover),
      );
    }
    if (label.contains('livraison')) {
      return box(const Color(0xFFFFF0E5), const Icon(Icons.payments_outlined, color: OvanieColors.orange, size: 28));
    }
    return box(OvanieColors.navy, const Icon(Icons.credit_card_rounded, color: Colors.white, size: 28));
  }
}

class _HelpCard extends StatelessWidget {
  const _HelpCard({required this.onContact});

  final VoidCallback onContact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      decoration: BoxDecoration(color: const Color(0xFFF2F4F7), borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: Color(0xFFE2E6EC),
                child: Icon(Icons.headset_mic_outlined, color: _navy, size: 22),
              ),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Besoin d’aide sur un paiement ?',
                      style: TextStyle(color: _navy, fontSize: 14.5, fontWeight: FontWeight.w700),
                    ),
                    SizedBox(height: 2),
                    Text('Notre équipe peut vous accompagner.', style: TextStyle(color: _muted, fontSize: 12.5)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 42,
            child: FilledButton(
              onPressed: onContact,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
              ),
              child: const Text('Nous contacter', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.filtered, required this.onShowAll, required this.onOrders, required this.onSupport});

  final bool filtered;
  final VoidCallback onShowAll;
  final VoidCallback onOrders;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 18),
      child: Column(
        children: [
          const Spacer(),
          Image.asset('assets/images/checkout/payment_history_empty.png', width: 170),
          const SizedBox(height: 18),
          Text(
            filtered ? 'Aucun paiement dans ce filtre' : 'Aucun paiement pour le moment',
            textAlign: TextAlign.center,
            style: const TextStyle(color: _navy, fontSize: 21, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              filtered
                  ? 'Aucune transaction ne correspond au statut sélectionné.'
                  : 'Vos paiements, reçus et remboursements apparaîtront ici après vos premières commandes.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: _muted, fontSize: 14, height: 1.45),
            ),
          ),
          const SizedBox(height: 12),
          TextButton(
            onPressed: filtered ? onShowAll : onOrders,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  filtered ? 'Afficher tous les paiements' : 'Voir mes commandes',
                  style: const TextStyle(color: OvanieColors.orange, fontSize: 14.5, fontWeight: FontWeight.w600),
                ),
                const Icon(Icons.chevron_right_rounded, color: OvanieColors.orange, size: 20),
              ],
            ),
          ),
          const Spacer(),
          _HelpCard(onContact: onSupport),
        ],
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
