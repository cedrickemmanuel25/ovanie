import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../catalog/presentation/catalog_screen.dart';
import '../../favorites/presentation/favorites_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/returns_repository.dart';
import '../domain/return_model.dart';

class ReturnDetailScreen extends StatefulWidget {
  final ClientReturnCase initial;
  const ReturnDetailScreen({super.key, required this.initial});

  @override
  State<ReturnDetailScreen> createState() => _ReturnDetailScreenState();
}

class _ReturnDetailScreenState extends State<ReturnDetailScreen> {
  final ReturnsRepository _repository = const ReturnsRepository();
  late ClientReturnCase _item = widget.initial;
  bool _loading = false;

  Future<void> _refresh() async {
    if (_loading) return;
    setState(() => _loading = true);
    try {
      final result = await _repository.fetchCase(_item.id);
      if (!mounted) return;
      setState(() {
        _item = result;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ApiClient.friendlyError(error))),
      );
    }
  }

  void _openSupport() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => SupportCenterScreen(
        initialCategory: 'returns',
        contextType: 'return',
        contextId: _item.id,
        initialSubject: 'Assistance retour ${_item.orderNumber.isNotEmpty ? _item.orderNumber : '#${_item.id}'}',
      )),
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
        child: RefreshIndicator(
          color: OvanieColors.orange,
          onRefresh: _refresh,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
            slivers: [
              SliverToBoxAdapter(child: _header()),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
                sliver: SliverList(
                  delegate: SliverChildListDelegate.fixed([
                    _productCard(),
                    const SizedBox(height: 20),
                    const Text('Statut du retour', style: TextStyle(color: OvanieColors.navy, fontSize: 18, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 14),
                    _ReturnTimeline(item: _item),
                    const SizedBox(height: 20),
                    _ReasonCard(item: _item),
                    const SizedBox(height: 14),
                    _ReturnLogisticsCard(item: _item),
                    const SizedBox(height: 14),
                    _RefundSummary(item: _item),
                    const SizedBox(height: 14),
                    _ImportantInfo(item: _item),
                    const SizedBox(height: 14),
                    _HelpCard(onTap: _openSupport),
                    const SizedBox(height: 14),
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _header() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 18, 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 29),
          ),
          const SizedBox(width: 3),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Détail du retour', style: TextStyle(color: OvanieColors.navy, fontSize: 24, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(
                  'Retour #RET-${_item.createdAt?.year ?? DateTime.now().year}-${_item.id.toString().padLeft(5, '0')}',
                  style: const TextStyle(color: Color(0xFF31456D), fontSize: 12.5, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          _StatusPill(item: _item),
        ],
      ),
    );
  }

  Widget _productCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _detailCardDecoration(),
      child: Row(
        children: [
          _ProductImage(url: _item.imageUrl, size: 100),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_item.productName, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 16, fontWeight: FontWeight.w900)),
                const SizedBox(height: 7),
                Text('Commande #${_item.orderNumber}', style: const TextStyle(color: Color(0xFF40547A), fontSize: 12)),
                if (_item.orderCreatedAt != null) ...[
                  const SizedBox(height: 5),
                  Text('Acheté le ${_date(_item.orderCreatedAt!)}', style: const TextStyle(color: Color(0xFF40547A), fontSize: 12)),
                ],
                if (_item.unitPrice != null) ...[
                  const SizedBox(height: 9),
                  Text(formatFcfa(_item.unitPrice!), style: const TextStyle(color: OvanieColors.orange, fontSize: 16, fontWeight: FontWeight.w900)),
                ],
                const SizedBox(height: 5),
                Text('Qté retournée : ${_item.quantity}', style: const TextStyle(color: Color(0xFF40547A), fontSize: 12)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: OvanieColors.navy, size: 27),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final ClientReturnCase item;
  const _StatusPill({required this.item});

  @override
  Widget build(BuildContext context) {
    Color color;
    Color background;
    String text;
    if (item.status == 'rejected' || item.status == 'cancelled') {
      color = const Color(0xFFD92D20);
      background = const Color(0xFFFFECEA);
      text = 'Refusé';
    } else if (item.status == 'refunded' || item.status == 'resolved' || item.status == 'closed') {
      color = OvanieColors.success;
      background = const Color(0xFFEAF8EF);
      text = 'Terminé';
    } else if (item.logisticsStatus == 'return_in_transit') {
      color = const Color(0xFF1262D8);
      background = const Color(0xFFEAF3FF);
      text = 'Expédié';
    } else {
      color = const Color(0xFFD87800);
      background = const Color(0xFFFFF3E3);
      text = 'En cours';
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(10)),
      child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w800)),
    );
  }
}

class _ReturnTimeline extends StatelessWidget {
  final ClientReturnCase item;
  const _ReturnTimeline({required this.item});

  @override
  Widget build(BuildContext context) {
    final steps = <_TimelineData>[
      _TimelineData('Demande\ninitiée', item.createdAt ?? item.requestDate, true),
      _TimelineData('Détails &\npreuves', item.updatedAt ?? item.createdAt, item.photoCount > 0 || item.detailedDescription.isNotEmpty),
      _TimelineData(
        'Livraison du\nretour',
        item.pickupDate ?? item.acceptedAt,
        item.hasPickupPlan || const {'return_in_transit', 'return_received', 'refund_pending', 'refunded'}.contains(item.logisticsStatus),
      ),
      _TimelineData(
        'Vérification &\nconfirmation',
        item.resolvedAt ?? item.refundedAt,
        item.isClosed,
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        return SizedBox(
          height: 126,
          child: Stack(
            children: [
              Positioned(left: constraints.maxWidth / 8, right: constraints.maxWidth / 8, top: 15, child: Container(height: 1.5, color: OvanieColors.navy)),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: steps.map((step) {
                  return Expanded(
                    child: Column(
                      children: [
                        Container(
                          width: 31,
                          height: 31,
                          decoration: BoxDecoration(
                            color: step.done ? OvanieColors.success : Colors.white,
                            shape: BoxShape.circle,
                            border: Border.all(color: step.done ? OvanieColors.success : OvanieColors.orange, width: 2),
                          ),
                          child: step.done ? const Icon(Icons.check_rounded, color: Colors.white, size: 19) : null,
                        ),
                        const SizedBox(height: 8),
                        Text(step.label, textAlign: TextAlign.center, style: TextStyle(color: step.done ? OvanieColors.navy : OvanieColors.orange, fontSize: 10.8, height: 1.3, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 6),
                        Text(step.date == null ? (step.done ? 'Validé' : 'En cours') : _dateTime(step.date!), textAlign: TextAlign.center, style: TextStyle(color: step.done ? const Color(0xFF40547A) : OvanieColors.orange, fontSize: 9.7, height: 1.25)),
                      ],
                    ),
                  );
                }).toList(growable: false),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _TimelineData {
  final String label;
  final DateTime? date;
  final bool done;
  const _TimelineData(this.label, this.date, this.done);
}

class _ReasonCard extends StatelessWidget {
  final ClientReturnCase item;
  const _ReasonCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final description = item.detailedDescription.trim().isNotEmpty ? item.detailedDescription.trim() : item.reason;
    return Container(
      padding: const EdgeInsets.all(17),
      decoration: _detailCardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Motif du retour', style: TextStyle(color: OvanieColors.navy, fontSize: 17, fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(width: 54, height: 54, decoration: const BoxDecoration(color: Color(0xFFFFEEE5), shape: BoxShape.circle), child: const Icon(Icons.inventory_2_outlined, color: OvanieColors.orange, size: 27)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.reason.isEmpty ? 'Retour produit' : item.reason, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.5, fontWeight: FontWeight.w900)),
                    if (description.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(description, style: const TextStyle(color: Color(0xFF40547A), fontSize: 11.5, height: 1.45)),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ReturnLogisticsCard extends StatelessWidget {
  final ClientReturnCase item;
  const _ReturnLogisticsCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final address = item.collectionAddress;
    final contact = item.pickupContactName.trim().isNotEmpty ? item.pickupContactName : item.recipientName;
    return Container(
      padding: const EdgeInsets.all(17),
      decoration: _detailCardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Informations de livraison du retour', style: TextStyle(color: OvanieColors.navy, fontSize: 17, fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(width: 54, height: 54, decoration: const BoxDecoration(color: Color(0xFFF4EAFE), shape: BoxShape.circle), child: const Icon(Icons.local_shipping_outlined, color: Color(0xFF8E35E8), size: 27)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const _InfoLine(label: 'Mode de retour', value: 'Collecte à l’adresse indiquée par OVANIE'),
                    if (address.isNotEmpty) _InfoLine(label: 'Adresse de collecte', value: address),
                    if (item.pickupDate != null) _InfoLine(label: 'Date de collecte souhaitée', value: _date(item.pickupDate!)),
                    if (item.pickupTimeSlot.isNotEmpty) _InfoLine(label: 'Créneau préféré', value: item.pickupTimeSlot),
                    if (contact.isNotEmpty) _InfoLine(label: 'Contact', value: contact),
                    if (item.logisticsStatusLabel.isNotEmpty) _InfoLine(label: 'Suivi', value: item.logisticsStatusLabel),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  final String label;
  final String value;
  const _InfoLine({required this.label, required this.value});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 9),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(value, style: const TextStyle(color: Color(0xFF40547A), fontSize: 11.5, height: 1.35))]),
      );
}

class _RefundSummary extends StatelessWidget {
  final ClientReturnCase item;
  const _RefundSummary({required this.item});

  @override
  Widget build(BuildContext context) {
    final double estimated = item.refundAmount ?? ((item.unitPrice ?? 0.0) * item.quantity);
    return Container(
      padding: const EdgeInsets.all(17),
      decoration: _detailCardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Récapitulatif', style: TextStyle(color: OvanieColors.navy, fontSize: 15, fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          Row(
            children: [
              Container(width: 50, height: 50, decoration: const BoxDecoration(color: Color(0xFFEAF8EF), shape: BoxShape.circle), child: const Icon(Icons.receipt_long_outlined, color: OvanieColors.success, size: 26)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  children: [
                    _AmountLine(label: 'Montant remboursable (estimé)', amount: estimated),
                    const SizedBox(height: 9),
                    const _AmountLine(label: 'Frais de retour', amount: 0.0, green: true),
                    const Divider(height: 20, color: OvanieColors.border),
                    _AmountLine(label: 'Total estimé du remboursement', amount: estimated, strong: true),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AmountLine extends StatelessWidget {
  final String label;
  final double amount;
  final bool strong;
  final bool green;
  const _AmountLine({required this.label, required this.amount, this.strong = false, this.green = false});
  @override
  Widget build(BuildContext context) => Row(
        children: [
          Expanded(child: Text(label, style: TextStyle(color: OvanieColors.navy, fontSize: 11.5, fontWeight: strong ? FontWeight.w900 : FontWeight.w500))),
          Text(formatFcfa(amount), style: TextStyle(color: green ? OvanieColors.success : OvanieColors.orange, fontSize: strong ? 14 : 12, fontWeight: FontWeight.w900)),
        ],
      );
}

class _ImportantInfo extends StatelessWidget {
  final ClientReturnCase item;
  const _ImportantInfo({required this.item});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(color: const Color(0xFFF6FAFF), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFD9E9FF))),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [const Icon(Icons.info_outline_rounded, color: Color(0xFF0A66E8), size: 28), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [const Text('Informations importantes', style: TextStyle(color: OvanieColors.navy, fontSize: 12.5, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(_importantText(item), style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.45))]))]),
      );
}

String _importantText(ClientReturnCase item) {
  if (item.status == 'rejected') return 'Votre demande a été examinée et refusée. Consultez les informations du dossier ou contactez le support OVANIE pour toute précision.';
  if (item.status == 'refunded' || item.logisticsStatus == 'refunded') return 'Votre retour est finalisé et le remboursement associé a été traité.';
  if (item.logisticsStatus == 'return_received') return 'Votre retour a été reçu et est en cours de vérification par notre équipe. Vous serez informé dès la prochaine étape.';
  return 'Votre retour est en cours de traitement par notre équipe. Vous serez notifié dès qu’une nouvelle étape sera confirmée.';
}

class _HelpCard extends StatelessWidget {
  final VoidCallback onTap;
  const _HelpCard({required this.onTap});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 14),
        decoration: BoxDecoration(color: const Color(0xFFFFF6EF), borderRadius: BorderRadius.circular(10)),
        child: Row(children: [const Icon(Icons.headset_mic_outlined, color: OvanieColors.orange, size: 31), const SizedBox(width: 12), const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Besoin d’aide ?', style: TextStyle(color: OvanieColors.navy, fontSize: 12.5, fontWeight: FontWeight.w900)), SizedBox(height: 3), Text('Notre équipe est disponible pour vous accompagner.', style: TextStyle(color: OvanieColors.navy, fontSize: 10.8))])), OutlinedButton(onPressed: onTap, style: OutlinedButton.styleFrom(foregroundColor: OvanieColors.orange, side: const BorderSide(color: OvanieColors.orange), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7))), child: const Text('Nous contacter', style: TextStyle(fontWeight: FontWeight.w800)))]),
      );
}

class _ProductImage extends StatelessWidget {
  final String url;
  final double size;
  const _ProductImage({required this.url, required this.size});
  @override
  Widget build(BuildContext context) => SizedBox(
        width: size,
        height: size,
        child: url.isEmpty
            ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB6C0D0), size: 48)
            : Image.network(url, fit: BoxFit.contain, errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: Color(0xFFB6C0D0), size: 48)),
      );
}

BoxDecoration _detailCardDecoration() => BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: OvanieColors.border),
    );

String _date(DateTime value) {
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
  return '${value.day} ${months[value.month - 1]} ${value.year}';
}

String _dateTime(DateTime value) {
  String two(int n) => n.toString().padLeft(2, '0');
  return '${_date(value)}\n${two(value.hour)}:${two(value.minute)}';
}
