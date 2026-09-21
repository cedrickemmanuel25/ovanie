import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../catalog/presentation/catalog_screen.dart';
import '../../favorites/presentation/favorites_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/returns_repository.dart';
import '../domain/return_model.dart';
import 'return_detail_screen.dart';
import 'return_form_screen.dart';

class ReturnsScreen extends StatefulWidget {
  const ReturnsScreen({super.key});

  @override
  State<ReturnsScreen> createState() => _ReturnsScreenState();
}

class _ReturnsScreenState extends State<ReturnsScreen> {
  final ReturnsRepository _repository = const ReturnsRepository();

  ReturnCenterData? _data;
  bool _loading = true;
  String? _error;
  bool _refundsTab = false;
  String _returnFilter = 'all';
  String _refundFilter = 'all';

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
      final data = await _repository.fetchCenter();
      if (!mounted) return;
      setState(() {
        _data = data;
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

  List<ClientReturnCase> get _returns => (_data?.requests ?? const <ClientReturnCase>[])
      .where((item) => item.type != 'refund')
      .toList(growable: false);

  List<ClientReturnCase> get _refunds => (_data?.requests ?? const <ClientReturnCase>[])
      .where((item) => item.type == 'refund')
      .toList(growable: false);

  List<ClientReturnCase> get _visibleReturns {
    if (_returnFilter == 'all') return _returns;
    return _returns.where((item) => _returnBucket(item) == _returnFilter).toList(growable: false);
  }

  List<ClientReturnCase> get _visibleRefunds {
    if (_refundFilter == 'all') return _refunds;
    return _refunds.where((item) => _refundBucket(item) == _refundFilter).toList(growable: false);
  }

  String _returnBucket(ClientReturnCase item) {
    if (item.status == 'rejected' || item.status == 'cancelled') return 'rejected';
    if (item.logisticsStatus == 'return_in_transit' ||
        item.logisticsStatus == 'return_received' ||
        item.logisticsStatus == 'refund_pending' ||
        item.logisticsStatus == 'refunded') {
      return 'shipped';
    }
    if (item.status == 'accepted' || item.status == 'closed' || item.status == 'resolved' || item.status == 'refunded') {
      return 'approved';
    }
    return 'ongoing';
  }

  String _refundBucket(ClientReturnCase item) {
    if (item.status == 'rejected' || item.status == 'cancelled') return 'rejected';
    if (item.status == 'refunded' || item.logisticsStatus == 'refunded') return 'paid';
    if (item.status == 'accepted' || item.status == 'closed' || item.status == 'resolved') return 'validated';
    return 'pending';
  }

  int _returnCount(String bucket) {
    if (bucket == 'all') return _returns.length;
    return _returns.where((item) => _returnBucket(item) == bucket).length;
  }

  int _refundCount(String bucket) {
    if (bucket == 'all') return _refunds.length;
    return _refunds.where((item) => _refundBucket(item) == bucket).length;
  }

  Future<void> _openCase(ClientReturnCase item) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => ReturnDetailScreen(initial: item)),
    );
    if (mounted) await _load();
  }

  Future<void> _prepareCase(ClientReturnCase item) async {
    final result = await Navigator.of(context).push(
      MaterialPageRoute<ClientReturnCase>(
        builder: (_) => ReturnFormScreen(existingCase: item),
      ),
    );
    if (mounted && result != null) await _load();
  }

  void _openCatalog() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const CatalogScreen(showBackButton: true)),
    );
  }

  void _openOrders() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const OrdersScreen()),
    );
  }

  void _openSupport() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()),
    );
  }

  void _openCart() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const CartScreen()),
    );
  }

  void _openFavorites() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const FavoritesScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: const OvanieBottomNavigation(
        selectedTab: OvanieMainTab.account,
      ),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _ReturnsHeader(onBack: () => Navigator.of(context).maybePop()),
            _PrimaryTabs(
              refundsSelected: _refundsTab,
              onChanged: (refunds) => setState(() => _refundsTab = refunds),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator(color: OvanieColors.orange));
    }

    if (_error != null && _data == null) {
      return RefreshIndicator(
        color: OvanieColors.orange,
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(28, 90, 28, 40),
          children: [
            const Icon(Icons.cloud_off_outlined, size: 62, color: OvanieColors.muted),
            const SizedBox(height: 18),
            Text(
              _error!,
              textAlign: TextAlign.center,
              style: const TextStyle(color: OvanieColors.text, fontSize: 15.5, height: 1.4),
            ),
            const SizedBox(height: 18),
            Center(
              child: OutlinedButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Réessayer'),
              ),
            ),
          ],
        ),
      );
    }

    return _refundsTab ? _refundsBody() : _returnsBody();
  }

  Widget _returnsBody() {
    final all = _returns;
    final visible = _visibleReturns;
    return RefreshIndicator(
      color: OvanieColors.orange,
      onRefresh: _load,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          SliverToBoxAdapter(
            child: Column(
              children: [
                const _InformationBanner(
                  line1: 'Consultez l’état de vos demandes de retour et suivez leur traitement.',
                  line2: 'Besoin d’aide ? Contactez notre support.',
                ),
                _FilterStrip(
                  items: [
                    _FilterData('all', 'Tous', _returnCount('all')),
                    _FilterData('ongoing', 'En cours', _returnCount('ongoing')),
                    _FilterData('approved', 'Approuvés', _returnCount('approved')),
                    _FilterData('shipped', 'Expédiés', _returnCount('shipped')),
                    _FilterData('rejected', 'Refusés', _returnCount('rejected')),
                  ],
                  selected: _returnFilter,
                  onSelected: (value) => setState(() => _returnFilter = value),
                ),
              ],
            ),
          ),
          if (visible.isEmpty)
            SliverToBoxAdapter(
              child: _ReturnEmptyState(
                filtered: all.isNotEmpty,
                onProducts: _openCatalog,
                onContact: _openSupport,
              ),
            )
          else ...[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, index) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _ReturnRequestCard(
                      item: visible[index],
                      onOpen: () => _openCase(visible[index]),
                      onPrepare: () => _prepareCase(visible[index]),
                    ),
                  ),
                  childCount: visible.length,
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 18),
                child: _HelpCard(
                  title: 'Besoin d’aide pour un retour ?',
                  subtitle: 'Notre équipe est disponible pour vous accompagner dans votre demande de retour.',
                  onContact: _openSupport,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _refundsBody() {
    final all = _refunds;
    final visible = _visibleRefunds;
    return RefreshIndicator(
      color: OvanieColors.orange,
      onRefresh: _load,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          SliverToBoxAdapter(
            child: Column(
              children: [
                const _InformationBanner(
                  line1: 'Consultez l’état de vos remboursements et suivez leur traitement.',
                  line2: 'Visualisez les montants reversés ou en attente de paiement.',
                ),
                _FilterStrip(
                  items: [
                    _FilterData('all', 'Tous', _refundCount('all')),
                    _FilterData('pending', 'En attente', _refundCount('pending')),
                    _FilterData('validated', 'Validés', _refundCount('validated')),
                    _FilterData('paid', 'Payés', _refundCount('paid')),
                    _FilterData('rejected', 'Refusés', _refundCount('rejected')),
                  ],
                  selected: _refundFilter,
                  onSelected: (value) => setState(() => _refundFilter = value),
                ),
              ],
            ),
          ),
          if (visible.isEmpty)
            SliverToBoxAdapter(
              child: _RefundEmptyState(
                filtered: all.isNotEmpty,
                onOrders: _openOrders,
                onHome: () => Navigator.of(context).popUntil((route) => route.isFirst),
                onContact: _openSupport,
              ),
            )
          else ...[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, index) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _RefundCard(
                      item: visible[index],
                      onOpen: () => _openCase(visible[index]),
                    ),
                  ),
                  childCount: visible.length,
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 18),
                child: _HelpCard(
                  title: 'Besoin d’aide pour un remboursement ?',
                  subtitle: 'Notre équipe est disponible pour vous accompagner.',
                  onContact: _openSupport,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ReturnsHeader extends StatelessWidget {
  final VoidCallback onBack;
  const _ReturnsHeader({required this.onBack});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 68,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Positioned(
            left: 18,
            child: IconButton(
              onPressed: onBack,
              icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 30),
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 58),
            child: Text(
              'Mes retours & remboursements',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: TextStyle(color: OvanieColors.navy, fontSize: 21, fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }
}

class _PrimaryTabs extends StatelessWidget {
  final bool refundsSelected;
  final ValueChanged<bool> onChanged;

  const _PrimaryTabs({required this.refundsSelected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 55,
      margin: const EdgeInsets.symmetric(horizontal: 18),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: OvanieColors.border)),
      ),
      child: Row(
        children: [
          Expanded(
            child: _PrimaryTabButton(
              label: 'Mes retours',
              selected: !refundsSelected,
              onTap: () => onChanged(false),
            ),
          ),
          Expanded(
            child: _PrimaryTabButton(
              label: 'Remboursements',
              selected: refundsSelected,
              onTap: () => onChanged(true),
            ),
          ),
        ],
      ),
    );
  }
}

class _PrimaryTabButton extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _PrimaryTabButton({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Text(
            label,
            style: TextStyle(
              color: selected ? OvanieColors.orange : OvanieColors.navy,
              fontSize: 15,
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
          if (selected)
            const Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: SizedBox(height: 2, child: ColoredBox(color: OvanieColors.orange)),
            ),
        ],
      ),
    );
  }
}

class _InformationBanner extends StatelessWidget {
  final String line1;
  final String line2;
  const _InformationBanner({required this.line1, required this.line2});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(18, 16, 18, 12),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        color: const Color(0xFFF6FBFF),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFCFE7FF)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline_rounded, color: Color(0xFF1592E6), size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: RichText(
              text: TextSpan(
                style: const TextStyle(color: OvanieColors.navy, fontSize: 12.5, height: 1.45, fontWeight: FontWeight.w500),
                children: [
                  TextSpan(text: '$line1\n'),
                  TextSpan(text: line2),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterData {
  final String key;
  final String label;
  final int count;
  const _FilterData(this.key, this.label, this.count);
}

class _FilterStrip extends StatelessWidget {
  final List<_FilterData> items;
  final String selected;
  final ValueChanged<String> onSelected;

  const _FilterStrip({required this.items, required this.selected, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 58,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final item = items[index];
          final active = item.key == selected;
          return InkWell(
            onTap: () => onSelected(item.key),
            borderRadius: BorderRadius.circular(10),
            child: Container(
              alignment: Alignment.center,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: active ? const Color(0xFFFFFAF7) : Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: active ? OvanieColors.orange : OvanieColors.border, width: active ? 1.3 : 1),
              ),
              child: Text(
                '${item.label} (${item.count})',
                style: TextStyle(
                  color: active ? OvanieColors.orange : const Color(0xFF30446B),
                  fontSize: 13,
                  fontWeight: active ? FontWeight.w800 : FontWeight.w500,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _ReturnRequestCard extends StatelessWidget {
  final ClientReturnCase item;
  final VoidCallback onOpen;
  final VoidCallback onPrepare;
  const _ReturnRequestCard({required this.item, required this.onOpen, required this.onPrepare});

  @override
  Widget build(BuildContext context) {
    final visual = _returnVisual(item);
    final price = item.unitPrice;
    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(13),
      child: Container(
        padding: const EdgeInsets.fromLTRB(13, 12, 12, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: OvanieColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _CaseStatusChip(visual: visual),
                const Spacer(),
                Text(
                  _caseDateLabel(item, refund: false),
                  style: const TextStyle(color: Color(0xFF30446B), fontSize: 10.5, fontWeight: FontWeight.w500),
                ),
                const SizedBox(width: 5),
                const Icon(Icons.chevron_right_rounded, color: OvanieColors.navy, size: 20),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 13,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _ProductImage(url: item.imageUrl),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.productName,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: OvanieColors.navy, fontSize: 13.2, height: 1.15, fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Commande #${item.orderNumber}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.5, fontWeight: FontWeight.w500),
                            ),
                            if (price != null) ...[
                              const SizedBox(height: 7),
                              Text(formatFcfa(price), style: const TextStyle(color: OvanieColors.orange, fontSize: 13.5, fontWeight: FontWeight.w800)),
                            ],
                            const SizedBox(height: 6),
                            Text('Qté : ${item.quantity}', style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.8, fontWeight: FontWeight.w500)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                Container(width: 1, margin: const EdgeInsets.symmetric(horizontal: 12), color: OvanieColors.border),
                Expanded(
                  flex: 10,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Motif du retour', style: TextStyle(color: OvanieColors.navy, fontSize: 10.8, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 5),
                      Text(
                        item.reason.isEmpty ? 'Motif non renseigné' : item.reason,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.8, height: 1.25),
                      ),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        height: 37,
                        child: visual.primaryAction
                            ? FilledButton(
                                onPressed: visual.actionLabel == 'Préparer le retour' ? onPrepare : onOpen,
                                style: FilledButton.styleFrom(
                                  backgroundColor: const Color(0xFF0B45F5),
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(horizontal: 8),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                                ),
                                child: FittedBox(fit: BoxFit.scaleDown, child: Text(visual.actionLabel, maxLines: 1, style: const TextStyle(fontSize: 10.7, fontWeight: FontWeight.w700))),
                              )
                            : OutlinedButton(
                                onPressed: visual.actionLabel == 'Préparer le retour' ? onPrepare : onOpen,
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFF0B45F5),
                                  side: const BorderSide(color: Color(0xFF0B45F5)),
                                  padding: const EdgeInsets.symmetric(horizontal: 8),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                                ),
                                child: FittedBox(fit: BoxFit.scaleDown, child: Text(visual.actionLabel, maxLines: 1, style: const TextStyle(fontSize: 10.7, fontWeight: FontWeight.w700))),
                              ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _RefundCard extends StatelessWidget {
  final ClientReturnCase item;
  final VoidCallback onOpen;
  const _RefundCard({required this.item, required this.onOpen});

  @override
  Widget build(BuildContext context) {
    final visual = _refundVisual(item);
    final amount = item.refundAmount ?? item.unitPrice;
    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(13),
      child: Container(
        padding: const EdgeInsets.fromLTRB(13, 12, 12, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: OvanieColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _CaseStatusChip(visual: visual),
                const Spacer(),
                Text(
                  _caseDateLabel(item, refund: true),
                  style: const TextStyle(color: Color(0xFF30446B), fontSize: 10.5, fontWeight: FontWeight.w500),
                ),
                const SizedBox(width: 5),
                const Icon(Icons.chevron_right_rounded, color: OvanieColors.navy, size: 20),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 13,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _ProductImage(url: item.imageUrl),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.productName,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: OvanieColors.navy, fontSize: 13.2, height: 1.15, fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Commande #${item.orderNumber}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.5, fontWeight: FontWeight.w500),
                            ),
                            if (amount != null) ...[
                              const SizedBox(height: 7),
                              Text(formatFcfa(amount), style: const TextStyle(color: OvanieColors.orange, fontSize: 13.5, fontWeight: FontWeight.w800)),
                            ],
                            const SizedBox(height: 6),
                            Text('Qté : ${item.quantity}', style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.8, fontWeight: FontWeight.w500)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                Container(width: 1, margin: const EdgeInsets.symmetric(horizontal: 12), color: OvanieColors.border),
                Expanded(
                  flex: 10,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Motif du remboursement', style: TextStyle(color: OvanieColors.navy, fontSize: 10.8, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 5),
                      Text(
                        item.reason.isEmpty ? 'Motif non renseigné' : item.reason,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.8, height: 1.25),
                      ),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        height: 37,
                        child: OutlinedButton(
                          onPressed: onOpen,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFF0B45F5),
                            side: const BorderSide(color: Color(0xFF0B45F5)),
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                          ),
                          child: FittedBox(fit: BoxFit.scaleDown, child: Text(visual.actionLabel, maxLines: 1, style: const TextStyle(fontSize: 10.7, fontWeight: FontWeight.w700))),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ProductImage extends StatelessWidget {
  final String url;
  const _ProductImage({required this.url});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 88,
      height: 92,
      child: url.isEmpty
          ? const Center(child: Icon(Icons.inventory_2_outlined, color: OvanieColors.muted, size: 38))
          : Image.network(
              url,
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) => const Center(child: Icon(Icons.inventory_2_outlined, color: OvanieColors.muted, size: 38)),
            ),
    );
  }
}

class _CaseVisual {
  final String label;
  final String actionLabel;
  final Color color;
  final Color background;
  final IconData icon;
  final bool primaryAction;

  const _CaseVisual({
    required this.label,
    required this.actionLabel,
    required this.color,
    required this.background,
    required this.icon,
    this.primaryAction = false,
  });
}

_CaseVisual _returnVisual(ClientReturnCase item) {
  if (item.status == 'rejected' || item.status == 'cancelled') {
    return const _CaseVisual(
      label: 'Refusé',
      actionLabel: 'Voir le détail',
      color: Color(0xFFE2231A),
      background: Color(0xFFFFEBEA),
      icon: Icons.cancel_outlined,
    );
  }
  if (item.logisticsStatus == 'return_pickup_planned') {
    return const _CaseVisual(
      label: 'Collecte planifiée',
      actionLabel: 'Voir le détail',
      color: Color(0xFF1469E8),
      background: Color(0xFFEAF2FF),
      icon: Icons.event_available_outlined,
    );
  }
  if (item.logisticsStatus == 'return_in_transit' || item.logisticsStatus == 'return_received' || item.logisticsStatus == 'refund_pending') {
    return const _CaseVisual(
      label: 'Expédié',
      actionLabel: 'Suivre le retour',
      color: Color(0xFF1469E8),
      background: Color(0xFFEAF2FF),
      icon: Icons.local_shipping_outlined,
    );
  }
  if (item.status == 'accepted' || item.status == 'closed' || item.status == 'resolved' || item.status == 'refunded') {
    return const _CaseVisual(
      label: 'Approuvé',
      actionLabel: 'Préparer le retour',
      color: Color(0xFF16845B),
      background: Color(0xFFE8F7EF),
      icon: Icons.check_circle_outline_rounded,
      primaryAction: true,
    );
  }
  return const _CaseVisual(
    label: 'En cours',
    actionLabel: 'Voir le détail',
    color: Color(0xFFE98300),
    background: Color(0xFFFFF3E5),
    icon: Icons.sync_rounded,
  );
}

_CaseVisual _refundVisual(ClientReturnCase item) {
  if (item.status == 'rejected' || item.status == 'cancelled') {
    return const _CaseVisual(
      label: 'Refusé',
      actionLabel: 'Voir le détail',
      color: Color(0xFFE2231A),
      background: Color(0xFFFFEBEA),
      icon: Icons.cancel_outlined,
    );
  }
  if (item.status == 'refunded' || item.logisticsStatus == 'refunded') {
    return const _CaseVisual(
      label: 'Payé',
      actionLabel: 'Voir le reçu',
      color: Color(0xFF1469E8),
      background: Color(0xFFEAF2FF),
      icon: Icons.account_balance_wallet_outlined,
    );
  }
  if (item.status == 'accepted' || item.status == 'closed' || item.status == 'resolved') {
    return const _CaseVisual(
      label: 'Validé',
      actionLabel: 'Voir le détail',
      color: Color(0xFF16845B),
      background: Color(0xFFE8F7EF),
      icon: Icons.check_circle_outline_rounded,
    );
  }
  return const _CaseVisual(
    label: 'En attente',
    actionLabel: 'Voir le détail',
    color: Color(0xFFE98300),
    background: Color(0xFFFFF3E5),
    icon: Icons.sync_rounded,
  );
}

class _CaseStatusChip extends StatelessWidget {
  final _CaseVisual visual;
  const _CaseStatusChip({required this.visual});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(color: visual.background, borderRadius: BorderRadius.circular(7)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(visual.icon, color: visual.color, size: 14),
          const SizedBox(width: 5),
          Text(visual.label, style: TextStyle(color: visual.color, fontSize: 10.5, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

String _caseDateLabel(ClientReturnCase item, {required bool refund}) {
  DateTime? date;
  String prefix;
  if (refund) {
    if (item.status == 'refunded' || item.logisticsStatus == 'refunded') {
      date = item.refundedAt ?? item.resolvedAt ?? item.updatedAt;
      prefix = 'Versé le';
    } else if (item.status == 'accepted' || item.status == 'closed' || item.status == 'resolved') {
      date = item.acceptedAt ?? item.updatedAt;
      prefix = 'Validé le';
    } else if (item.status == 'rejected') {
      date = item.rejectedAt ?? item.updatedAt;
      prefix = 'Refusé le';
    } else {
      date = item.requestDate ?? item.createdAt;
      prefix = 'Demandé le';
    }
  } else {
    if (item.status == 'rejected') {
      date = item.rejectedAt ?? item.updatedAt;
      prefix = 'Refusé le';
    } else if (item.logisticsStatus == 'return_in_transit' || item.logisticsStatus == 'return_received') {
      date = item.updatedAt;
      prefix = 'Expédié le';
    } else if (item.status == 'accepted' || item.status == 'closed' || item.status == 'resolved' || item.status == 'refunded') {
      date = item.acceptedAt ?? item.updatedAt;
      prefix = 'Approuvé le';
    } else {
      date = item.requestDate ?? item.createdAt;
      prefix = 'Demande le';
    }
  }
  if (date == null) return prefix;
  return '$prefix ${_frenchDate(date)}';
}

String _frenchDate(DateTime date) {
  const months = <String>['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

class _ReturnEmptyState extends StatelessWidget {
  final bool filtered;
  final VoidCallback onProducts;
  final VoidCallback onContact;

  const _ReturnEmptyState({required this.filtered, required this.onProducts, required this.onContact});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 20, 18, 30),
      child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(24, 30, 24, 30),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: OvanieColors.border),
              ),
              child: Column(
                children: [
                  SizedBox(
                    width: 310,
                    height: 235,
                    child: Image.asset('assets/images/returns_empty_illustration.png', fit: BoxFit.contain),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    filtered ? 'Aucun retour dans ce statut' : 'Aucune demande de retour',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: OvanieColors.navy, fontSize: 23, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    filtered
                        ? 'Aucune demande ne correspond au filtre sélectionné.'
                        : 'Vous n’avez encore soumis aucune demande de retour.\nVos retours apparaîtront ici dès qu’une demande\nsera créée.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Color(0xFF33425F), fontSize: 14.2, height: 1.45, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    height: 49,
                    width: 250,
                    child: OutlinedButton.icon(
                      onPressed: onProducts,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF0B45F5),
                        side: const BorderSide(color: Color(0xFF0B45F5)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                      ),
                      icon: const Icon(Icons.shopping_bag_outlined),
                      label: const FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text('Découvrir nos produits', maxLines: 1, style: TextStyle(fontWeight: FontWeight.w800)),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 22),
            _HelpCard(
              title: 'Besoin d’aide pour un retour ?',
              subtitle: 'Notre équipe est disponible pour vous accompagner.',
              onContact: onContact,
            ),
          ],
      ),
    );
  }
}

class _RefundEmptyState extends StatelessWidget {
  final bool filtered;
  final VoidCallback onOrders;
  final VoidCallback onHome;
  final VoidCallback onContact;

  const _RefundEmptyState({required this.filtered, required this.onOrders, required this.onHome, required this.onContact});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 32, 18, 30),
      child: Column(
          children: [
            SizedBox(
              width: 300,
              height: 250,
              child: Image.asset('assets/images/refunds_empty_illustration.png', fit: BoxFit.contain),
            ),
            const SizedBox(height: 4),
            Text(
              filtered ? 'Aucun remboursement dans ce statut' : 'Aucun remboursement pour le moment',
              textAlign: TextAlign.center,
              style: const TextStyle(color: OvanieColors.navy, fontSize: 22, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            Text(
              filtered
                  ? 'Aucun remboursement ne correspond au filtre sélectionné.'
                  : 'Vous n’avez encore aucune demande de remboursement\nenregistrée. Vos remboursements validés apparaîtront ici\nautomatiquement.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFF33425F), fontSize: 14.2, height: 1.45, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 28),
            Row(
              children: [
                Expanded(
                  child: SizedBox(
                    height: 50,
                    child: FilledButton(
                      onPressed: onOrders,
                      style: FilledButton.styleFrom(
                        backgroundColor: OvanieColors.orange,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                      ),
                      child: const FittedBox(fit: BoxFit.scaleDown, child: Text('Voir mes commandes', maxLines: 1, style: TextStyle(fontWeight: FontWeight.w800))),
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: SizedBox(
                    height: 50,
                    child: OutlinedButton(
                      onPressed: onHome,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF0B45F5),
                        side: const BorderSide(color: Color(0xFF0B45F5)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                      ),
                      child: const FittedBox(fit: BoxFit.scaleDown, child: Text('Retour à l’accueil', maxLines: 1, style: TextStyle(fontWeight: FontWeight.w800))),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 56),
            _HelpCard(
              title: 'Besoin d’aide pour un remboursement ?',
              subtitle: 'Notre équipe est disponible pour vous accompagner.',
              onContact: onContact,
            ),
          ],
      ),
    );
  }
}

class _HelpCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback onContact;

  const _HelpCard({required this.title, required this.subtitle, required this.onContact});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(18, 15, 14, 15),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7F1),
        borderRadius: BorderRadius.circular(11),
      ),
      child: Row(
        children: [
          Container(
            width: 45,
            height: 45,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.headset_mic_outlined, color: OvanieColors.orange, size: 27),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 12.5, fontWeight: FontWeight.w800)),
                const SizedBox(height: 3),
                Text(subtitle, style: const TextStyle(color: Color(0xFF30446B), fontSize: 10.8, height: 1.25)),
              ],
            ),
          ),
          const SizedBox(width: 10),
          SizedBox(
            height: 40,
            child: OutlinedButton(
              onPressed: onContact,
              style: OutlinedButton.styleFrom(
                foregroundColor: OvanieColors.orange,
                side: const BorderSide(color: OvanieColors.orange),
                padding: const EdgeInsets.symmetric(horizontal: 15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
              ),
              child: const Text('Nous contacter', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}
