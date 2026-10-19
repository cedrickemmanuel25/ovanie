import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import 'package:flutter/services.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../catalog/presentation/catalog_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../payments/presentation/payment_ui.dart';
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

  List<ClientReturnCase> get _returns =>
      (_data?.requests ?? const <ClientReturnCase>[]).where((item) => item.type != 'refund').toList(growable: false);

  // Un retour remboursé figure aussi parmi les remboursements du client.
  List<ClientReturnCase> get _refunds => (_data?.requests ?? const <ClientReturnCase>[])
      .where(
        (item) =>
            item.type == 'refund' ||
            ((item.status == 'refunded' || item.logisticsStatus == 'refunded') && (item.refundAmount ?? 0) > 0),
      )
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
    if (item.status == 'refunded' || item.logisticsStatus == 'refunded') return 'approved';
    if (item.logisticsStatus == 'return_in_transit' ||
        item.logisticsStatus == 'return_received' ||
        item.logisticsStatus == 'refund_pending') {
      return 'shipped';
    }
    if (item.status == 'accepted' ||
        item.status == 'closed' ||
        item.status == 'resolved' ||
        item.status == 'refunded') {
      return 'approved';
    }
    return 'ongoing';
  }

  String _refundBucket(ClientReturnCase item) {
    if (item.status == 'rejected' || item.status == 'cancelled') return 'rejected';
    if (item.status == 'refunded' || item.logisticsStatus == 'refunded') return 'paid';
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
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ReturnDetailScreen(initial: item)));
    if (mounted) await _load();
  }

  Future<void> _prepareCase(ClientReturnCase item) async {
    final result = await Navigator.of(
      context,
    ).push(MaterialPageRoute<ClientReturnCase>(builder: (_) => ReturnFormScreen(existingCase: item)));
    if (mounted && result != null) await _load();
  }

  void _openCatalog() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const CatalogScreen(showBackButton: true)));
  }

  void _openOrders() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const OrdersScreen()));
  }

  void _openSupport() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: const Color(0xFFF4F5F8),
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _ReturnsHeader(onBack: () => Navigator.of(context).maybePop()),
              _PrimaryTabs(refundsSelected: _refundsTab, onChanged: (refunds) => setState(() => _refundsTab = refunds)),
              Expanded(child: _buildBody()),
            ],
          ),
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
              child: _ReturnEmptyState(filtered: all.isNotEmpty, onProducts: _openCatalog, onContact: _openSupport),
            )
          else ...[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, index) => Padding(
                    padding: const EdgeInsets.only(bottom: 14),
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
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 20),
              sliver: SliverToBoxAdapter(
                child: _HelpCard(
                  title: 'Besoin d’aide pour un retour ?',
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
                _FilterStrip(
                  items: [
                    _FilterData('all', 'Tous', _refundCount('all')),
                    _FilterData('pending', 'En cours', _refundCount('pending')),
                    _FilterData('paid', 'Effectués', _refundCount('paid')),
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
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, index) => _RefundCard(item: visible[index], onOpen: () => _openCase(visible[index])),
                  childCount: visible.length,
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
              sliver: SliverToBoxAdapter(
                child: _HelpCard(
                  title: 'Besoin d’aide pour un retour ?',
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
  Widget build(BuildContext context) => SizedBox(
    height: 64,
    child: Row(
      children: [
        const SizedBox(width: 14),
        Material(
          color: Colors.white,
          shape: const CircleBorder(),
          elevation: 2,
          shadowColor: const Color(0x26000000),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: onBack,
            child: const SizedBox(
              width: 50,
              height: 50,
              child: Icon(Icons.chevron_left_rounded, color: payNavy, size: 32),
            ),
          ),
        ),
        const Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              'Retours et remboursements',
              style: TextStyle(color: payNavy, fontSize: 23, fontWeight: FontWeight.w800),
            ),
          ),
        ),
        const SizedBox(width: 64),
      ],
    ),
  );
}

class _PrimaryTabs extends StatelessWidget {
  final bool refundsSelected;
  final ValueChanged<bool> onChanged;

  const _PrimaryTabs({required this.refundsSelected, required this.onChanged});

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
    padding: const EdgeInsets.all(5),
    decoration: BoxDecoration(color: const Color(0xFFE9EDF2), borderRadius: BorderRadius.circular(16)),
    child: Row(
      children: [
        _PrimaryTabButton(label: 'Mes retours', selected: !refundsSelected, onTap: () => onChanged(false)),
        _PrimaryTabButton(label: 'Remboursements', selected: refundsSelected, onTap: () => onChanged(true)),
      ],
    ),
  );
}

class _PrimaryTabButton extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _PrimaryTabButton({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) => Expanded(
    child: GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 50,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? Colors.white : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
          boxShadow: selected ? const [BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2))] : null,
        ),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            label,
            style: TextStyle(
              color: selected ? payNavy : payMuted,
              fontSize: 17,
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ),
      ),
    ),
  );
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
  Widget build(BuildContext context) => SizedBox(
    height: 50,
    child: ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      scrollDirection: Axis.horizontal,
      itemCount: items.length,
      separatorBuilder: (_, __) => const SizedBox(width: 10),
      itemBuilder: (context, index) {
        final item = items[index];
        final active = item.key == selected;
        return InkWell(
          onTap: () => onSelected(item.key),
          borderRadius: BorderRadius.circular(25),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: active ? payNavy : const Color(0xFFEFF2F6),
              borderRadius: BorderRadius.circular(25),
              border: Border.all(color: active ? payNavy : const Color(0xFFE1E6EE)),
            ),
            child: Text(
              '${item.label} (${item.count})',
              style: TextStyle(
                color: active ? Colors.white : const Color(0xFF4A556E),
                fontSize: 15.5,
                fontWeight: active ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ),
        );
      },
    ),
  );
}

class _ReturnRequestCard extends StatelessWidget {
  final ClientReturnCase item;
  final VoidCallback onOpen;
  final VoidCallback onPrepare;
  const _ReturnRequestCard({required this.item, required this.onOpen, required this.onPrepare});

  @override
  Widget build(BuildContext context) {
    final visual = _returnVisual(item);
    final Widget action;
    if (visual.actionLabel == 'Préparer le retour') {
      action = PaymentButton(label: 'Préparer le retour', height: 46, onPressed: onPrepare);
    } else if (visual.actionLabel == 'Suivre le retour') {
      action = PaymentButton(label: 'Suivre le retour', outlined: true, height: 46, onPressed: onOpen);
    } else {
      action = InkWell(
        onTap: onOpen,
        child: const Padding(
          padding: EdgeInsets.symmetric(vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Voir le détail',
                style: TextStyle(color: payBlue, fontSize: 16.5, fontWeight: FontWeight.w600),
              ),
              SizedBox(width: 6),
              Icon(Icons.chevron_right_rounded, color: payBlue, size: 24),
            ],
          ),
        ),
      );
    }
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: payBorder),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              OrderItemThumbnail(url: item.imageUrl, width: 78, height: 86),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.productName,
                                maxLines: 3,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: payNavy, fontSize: 14.5, fontWeight: FontWeight.w800),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Commande #${item.orderNumber}',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: payMuted, fontSize: 13.5),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            _CaseStatusChip(visual: visual),
                            const SizedBox(height: 6),
                            Text(
                              _caseDateLabel(item, refund: false),
                              style: const TextStyle(color: payMuted, fontSize: 12.5),
                            ),
                          ],
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Motif : ${item.reason.isEmpty ? 'non renseigné' : _lower(item.reason)}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: payMuted, fontSize: 13.5),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Text.rich(
                          TextSpan(
                            text: 'Qté ',
                            style: const TextStyle(color: payMuted, fontSize: 14),
                            children: [
                              TextSpan(
                                text: '${item.quantity}',
                                style: const TextStyle(color: payNavy, fontWeight: FontWeight.w800),
                              ),
                            ],
                          ),
                        ),
                        const Spacer(),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Align(alignment: Alignment.centerRight, child: action),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

String _lower(String value) {
  final clean = value.trim();
  if (clean.length < 2) return clean;
  return clean[0].toLowerCase() + clean.substring(1);
}

class _RefundCard extends StatelessWidget {
  final ClientReturnCase item;
  final VoidCallback onOpen;
  const _RefundCard({required this.item, required this.onOpen});

  @override
  Widget build(BuildContext context) {
    final visual = _refundVisual(item);
    final amount = item.refundAmount ?? ((item.unitPrice ?? 0) * item.quantity);
    final rejected = item.status == 'rejected' || item.status == 'cancelled';
    final paid = item.status == 'refunded' || item.logisticsStatus == 'refunded';
    final code = operatorCodeOf(item.refundMethodLabel);
    Widget line(String label, Widget value) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Text(label, style: const TextStyle(color: payMuted, fontSize: 14)),
          const SizedBox(width: 10),
          Expanded(
            child: Align(alignment: Alignment.centerRight, child: value),
          ),
        ],
      ),
    );
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: payBorder),
      ),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              OrderItemThumbnail(url: item.imageUrl, width: 78, height: 82),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.productName,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: payNavy, fontSize: 14.5, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text('Commande #${item.orderNumber}', style: const TextStyle(color: payMuted, fontSize: 13.5)),
                    Text('Qté ${item.quantity}', style: const TextStyle(color: payMuted, fontSize: 13.5)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  _CaseStatusChip(visual: visual, showIcon: false),
                  const SizedBox(height: 6),
                  SizedBox(
                    width: 110,
                    child: Text(
                      _caseDateLabel(item, refund: true),
                      textAlign: TextAlign.right,
                      style: const TextStyle(color: payMuted, fontSize: 12.5),
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
            decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(10)),
            child: Column(
              children: [
                line(
                  paid
                      ? 'Montant remboursé'
                      : rejected
                      ? 'Montant demandé'
                      : 'Montant à rembourser',
                  Text(
                    formatFcfa(amount),
                    style: const TextStyle(color: payNavy, fontSize: 15, fontWeight: FontWeight.w800),
                  ),
                ),
                if (rejected)
                  line(
                    'Motif du refus',
                    Text(
                      item.vendorResponse.trim().isEmpty ? 'Non précisé' : item.vendorResponse.trim(),
                      textAlign: TextAlign.right,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: payNavy, fontSize: 13.5),
                    ),
                  )
                else ...[
                  line(
                    'Méthode de remboursement',
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (code.isNotEmpty && code != 'card') ...[
                          PaymentOperatorLogo(code: code, size: 24),
                          const SizedBox(width: 8),
                        ],
                        Flexible(
                          child: Text(
                            item.refundMethodLabel.trim().isEmpty
                                ? 'Moyen de paiement initial'
                                : item.refundMethodLabel,
                            textAlign: TextAlign.right,
                            style: const TextStyle(color: payNavy, fontSize: 13.5),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (item.refundReference.trim().isNotEmpty)
                    line(
                      'Référence',
                      Text(item.refundReference, style: const TextStyle(color: payNavy, fontSize: 13.5)),
                    ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: OutlinedButton(
              onPressed: onOpen,
              style: OutlinedButton.styleFrom(
                foregroundColor: payNavy,
                side: const BorderSide(color: payNavy, width: 1.4),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Row(
                children: [
                  Expanded(
                    child: Text(
                      'Voir le détail',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                  ),
                  Icon(Icons.chevron_right_rounded, size: 24),
                ],
              ),
            ),
          ),
        ],
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
      color: Color(0xFFDC2626),
      background: Color(0xFFFFE8E8),
      icon: Icons.close_rounded,
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
  if (item.status == 'refunded' || item.logisticsStatus == 'refunded') {
    return const _CaseVisual(
      label: 'Remboursé',
      actionLabel: 'Voir le détail',
      color: Color(0xFF1469E8),
      background: Color(0xFFEAF2FF),
      icon: Icons.account_balance_wallet_outlined,
    );
  }
  if (item.logisticsStatus == 'return_in_transit' ||
      item.logisticsStatus == 'return_received' ||
      item.logisticsStatus == 'refund_pending') {
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
      color: Color(0xFF15803D),
      background: Color(0xFFE6F6EC),
      icon: Icons.check_rounded,
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
      color: Color(0xFFDC2626),
      background: Color(0xFFFFE8E8),
      icon: Icons.close_rounded,
    );
  }
  if (item.status == 'refunded' || item.logisticsStatus == 'refunded') {
    return const _CaseVisual(
      label: 'Effectué',
      actionLabel: 'Voir le reçu',
      color: Color(0xFF15803D),
      background: Color(0xFFE6F6EC),
      icon: Icons.account_balance_wallet_outlined,
    );
  }
  return const _CaseVisual(
    label: 'En cours',
    actionLabel: 'Voir le détail',
    color: Color(0xFFC2410C),
    background: Color(0xFFFFF1E6),
    icon: Icons.schedule_rounded,
  );
}

class _CaseStatusChip extends StatelessWidget {
  final _CaseVisual visual;
  final bool showIcon;
  const _CaseStatusChip({required this.visual, this.showIcon = true});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(color: visual.background, borderRadius: BorderRadius.circular(9)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (showIcon) ...[
            Container(
              width: 20,
              height: 20,
              decoration: BoxDecoration(color: visual.color, shape: BoxShape.circle),
              child: Icon(visual.icon, color: Colors.white, size: 14),
            ),
            const SizedBox(width: 6),
          ],
          Text(
            visual.label,
            style: TextStyle(color: visual.color, fontSize: 15, fontWeight: FontWeight.w600),
          ),
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
      prefix = 'Remboursé le';
    } else if (item.status == 'accepted' || item.status == 'closed' || item.status == 'resolved') {
      date = item.acceptedAt ?? item.updatedAt;
      prefix = 'Validé le';
    } else if (item.status == 'rejected') {
      date = item.rejectedAt ?? item.updatedAt;
      prefix = 'Refusé le';
    } else {
      date = item.requestDate ?? item.createdAt;
      prefix = 'Demande du';
    }
  } else {
    if (item.status == 'rejected') {
      date = item.rejectedAt ?? item.updatedAt;
      prefix = 'Refusé le';
    } else if (item.status == 'refunded' || item.logisticsStatus == 'refunded') {
      date = item.refundedAt ?? item.resolvedAt ?? item.updatedAt;
      prefix = 'Remboursé le';
    } else if (item.logisticsStatus == 'return_in_transit' || item.logisticsStatus == 'return_received') {
      date = item.updatedAt;
      prefix = 'Expédié le';
    } else if (item.status == 'accepted' ||
        item.status == 'closed' ||
        item.status == 'resolved' ||
        item.status == 'refunded') {
      date = item.acceptedAt ?? item.updatedAt;
      prefix = 'Approuvé le';
    } else {
      date = item.requestDate ?? item.createdAt;
      prefix = 'Demandé le';
    }
  }
  if (date == null) return prefix;
  return '$prefix ${_frenchDate(date)}';
}

String _frenchDate(DateTime date) {
  const months = <String>[
    'janv.',
    'févr.',
    'mars',
    'avr.',
    'mai',
    'juin',
    'juil.',
    'août',
    'sept.',
    'oct.',
    'nov.',
    'déc.',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

class _ReturnEmptyState extends StatelessWidget {
  final bool filtered;
  final VoidCallback onProducts;
  final VoidCallback onContact;

  const _ReturnEmptyState({required this.filtered, required this.onProducts, required this.onContact});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 56, 16, 30),
      child: Column(
        children: [
          Image.asset(
            'assets/images/checkout/returns_empty.png',
            width: (width * .66).clamp(200.0, 300.0),
            fit: BoxFit.contain,
          ),
          const SizedBox(height: 30),
          Text(
            filtered ? 'Aucun retour dans ce statut' : 'Aucune demande de retour',
            textAlign: TextAlign.center,
            style: const TextStyle(color: payNavy, fontSize: 25, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          Text(
            filtered
                ? 'Aucune demande ne correspond au filtre sélectionné.'
                : 'Vous n’avez encore soumis aucune demande de retour. Vos retours apparaîtront ici dès qu’une demande sera créée.',
            textAlign: TextAlign.center,
            style: const TextStyle(color: payMuted, fontSize: 16, height: 1.45),
          ),
          const SizedBox(height: 28),
          PaymentButton(label: 'Découvrir nos produits', height: 56, onPressed: onProducts),
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

  const _RefundEmptyState({
    required this.filtered,
    required this.onOrders,
    required this.onHome,
    required this.onContact,
  });

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
                    child: const FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text('Voir mes commandes', maxLines: 1, style: TextStyle(fontWeight: FontWeight.w800)),
                    ),
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
                    child: const FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text('Retour à l’accueil', maxLines: 1, style: TextStyle(fontWeight: FontWeight.w800)),
                    ),
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
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    child: InkWell(
      onTap: onContact,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 18, 12, 18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: payBorder),
        ),
        child: Row(
          children: [
            Container(
              width: 70,
              height: 70,
              decoration: const BoxDecoration(color: Color(0xFFEFF3F9), shape: BoxShape.circle),
              child: const Icon(Icons.headset_mic_outlined, color: payNavy, size: 36),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(subtitle, style: const TextStyle(color: payMuted, fontSize: 14.5)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: payNavy, size: 28),
          ],
        ),
      ),
    ),
  );
}
