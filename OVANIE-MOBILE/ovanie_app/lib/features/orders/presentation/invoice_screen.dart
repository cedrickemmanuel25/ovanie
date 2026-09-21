import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../data/orders_repository.dart';
import '../domain/order_model.dart';
import 'order_detail_screen.dart';

class InvoiceScreen extends StatefulWidget {
  final int orderId;
  final MobileOrder? initialOrder;

  const InvoiceScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });

  @override
  State<InvoiceScreen> createState() => _InvoiceScreenState();
}

class _InvoiceScreenState extends State<InvoiceScreen> {
  final _repository = const OrdersRepository();
  MobileOrder? _order;
  bool _loading = true;
  bool _downloading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _order = widget.initialOrder;
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = _order == null;
        _error = null;
      });
    }
    try {
      final order = await _repository.fetchOrder(widget.orderId);
      if (!mounted) return;
      setState(() {
        _order = order;
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

  Future<void> _download({bool share = false}) async {
    final order = _order;
    if (order == null || _downloading) return;
    setState(() => _downloading = true);
    try {
      final file = await _repository.downloadInvoice(order);
      if (!mounted) return;
      await OpenFilex.open(file.path);
      if (!mounted) return;
      if (share) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Le PDF est ouvert. Utilisez le bouton Partager de votre lecteur PDF.',
            ),
          ),
        );
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ApiClient.friendlyError(error))),
      );
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy),
        ),
        title: const Text(
          'Facture',
          style: TextStyle(
            color: OvanieColors.navy,
            fontWeight: FontWeight.w900,
            fontSize: 22,
          ),
        ),
        actions: [
          IconButton(
            tooltip: 'Partager',
            onPressed: order == null || _downloading ? null : () => _download(share: true),
            icon: const Icon(Icons.ios_share_rounded, color: OvanieColors.navy),
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : order == null
              ? _InvoiceError(message: _error ?? 'Cette facture est indisponible.', onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _InvoiceBody(
                    order: order,
                    downloading: _downloading,
                    onDownload: () => _download(),
                    onShare: () => _download(share: true),
                  ),
                ),
    );
  }
}

class _InvoiceBody extends StatelessWidget {
  final MobileOrder order;
  final bool downloading;
  final VoidCallback onDownload;
  final VoidCallback onShare;

  const _InvoiceBody({
    required this.order,
    required this.downloading,
    required this.onDownload,
    required this.onShare,
  });

  @override
  Widget build(BuildContext context) {
    final session = SessionStore.instance;
    final invoiceNumber = order.invoiceNumber.trim().isNotEmpty
        ? order.invoiceNumber.trim()
        : 'FAC-${order.orderNumber}';
    final billedName = (session.name ?? '').trim().isNotEmpty
        ? session.name!.trim()
        : (order.deliveryRecipientName.trim().isNotEmpty
            ? order.deliveryRecipientName.trim()
            : 'Client OVANIE');
    final phone = order.deliveryPhone.trim().isNotEmpty
        ? formatCiPhoneDisplay(order.deliveryPhone)
        : formatCiPhoneDisplay(session.phone ?? '');
    final email = (session.email ?? '').trim();
    final addressTitle = order.quartier.trim().isNotEmpty
        ? order.quartier.trim()
        : (order.commune.trim().isNotEmpty ? order.commune.trim() : 'Adresse de livraison');
    final paid = order.isPaymentConfirmed;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 8, 14, 28),
      children: [
        _InvoiceCard(
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 380;
              if (compact) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _InvoiceIdentity(
                      invoiceNumber: invoiceNumber,
                      orderNumber: order.orderNumber,
                      emittedAt: order.createdAt,
                      paid: paid,
                    ),
                    const SizedBox(height: 14),
                    const Divider(height: 1),
                    const SizedBox(height: 14),
                    _InvoiceTotal(total: order.total),
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Expanded(
                    flex: 3,
                    child: _InvoiceIdentity(
                      invoiceNumber: invoiceNumber,
                      orderNumber: order.orderNumber,
                      emittedAt: order.createdAt,
                      paid: paid,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Container(width: 1, height: 96, color: OvanieColors.border),
                  const SizedBox(width: 16),
                  Expanded(flex: 2, child: _InvoiceTotal(total: order.total)),
                ],
              );
            },
          ),
        ),
        const SizedBox(height: 10),
        _InvoiceCard(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final narrow = constraints.maxWidth < 410;
              final billed = _PartyBlock(
                icon: Icons.receipt_long_outlined,
                title: 'Facturé à',
                lines: [billedName, if (phone.isNotEmpty) phone, if (email.isNotEmpty) email],
              );
              final delivery = _PartyBlock(
                icon: Icons.location_on_outlined,
                title: 'Adresse de livraison',
                lines: [
                  addressTitle,
                  if (order.address.trim().isNotEmpty) order.address.trim(),
                  [order.city, order.commune].where((e) => e.trim().isNotEmpty).toSet().join(', '),
                ].where((e) => e.trim().isNotEmpty).toList(growable: false),
              );
              if (narrow) {
                return Column(
                  children: [
                    billed,
                    const SizedBox(height: 14),
                    const Divider(height: 1),
                    const SizedBox(height: 14),
                    delivery,
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: billed),
                  Container(width: 1, height: 120, color: OvanieColors.border),
                  const SizedBox(width: 18),
                  Expanded(child: delivery),
                ],
              );
            },
          ),
        ),
        const SizedBox(height: 10),
        _InvoiceCard(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Détail de la facture',
                style: _InvoiceText.sectionTitle,
              ),
              const SizedBox(height: 10),
              const _InvoiceTableHeader(),
              ...order.items.map((item) => _InvoiceItemRow(item: item)),
            ],
          ),
        ),
        const SizedBox(height: 10),
        _InvoiceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Récapitulatif', style: _InvoiceText.sectionTitle),
              const SizedBox(height: 8),
              _MoneyRow(label: 'Sous-total', value: order.subtotal),
              const SizedBox(height: 8),
              _MoneyRow(label: 'Livraison', value: order.deliveryFee),
              if (order.discount + order.loyaltyDiscount > 0) ...[
                const SizedBox(height: 8),
                _MoneyRow(
                  label: 'Remise',
                  value: -(order.discount + order.loyaltyDiscount),
                  accent: true,
                ),
              ],
              const SizedBox(height: 10),
              const Divider(height: 1),
              const SizedBox(height: 10),
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Total payé',
                      style: TextStyle(
                        color: OvanieColors.navy,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  Text(
                    formatFcfa(order.total),
                    style: const TextStyle(
                      color: OvanieColors.orange,
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        _InvoiceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Paiement', style: _InvoiceText.sectionTitle),
              const SizedBox(height: 12),
              LayoutBuilder(
                builder: (context, constraints) {
                  final method = _paymentLabel(order);
                  final reference = order.paymentReference.trim().isNotEmpty
                      ? order.paymentReference.trim()
                      : '—';
                  final date = order.paymentAttemptCreatedAt ?? order.createdAt;
                  final items = <Widget>[
                    _PaymentField(label: 'Méthode', value: method, leading: _paymentLogo(order)),
                    _PaymentField(label: 'Référence', value: reference),
                    _PaymentField(label: 'Date', value: _formatDateTime(date)),
                    _PaymentField(
                      label: 'Statut',
                      value: paid ? 'Transaction réussie' : order.paymentStatusLabel,
                      badge: paid,
                    ),
                  ];
                  if (constraints.maxWidth < 440) {
                    return Column(
                      children: items
                          .map((item) => Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: item,
                              ))
                          .toList(growable: false),
                    );
                  }
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: items
                        .map((item) => Expanded(child: Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: item,
                            )))
                        .toList(growable: false),
                  );
                },
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: const Color(0xFFF8FBFF),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: const Color(0xFFDCE8FF)),
          ),
          child: const Row(
            children: [
              Icon(Icons.info_outline_rounded, color: Color(0xFF1266F1), size: 20),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Cette facture est générée par OVANIE et sert de justificatif d’achat.',
                  style: TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.35),
                ),
              ),
              SizedBox(width: 8),
              _TaxBadge(),
            ],
          ),
        ),
        const SizedBox(height: 10),
        InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => OrderDetailScreen(orderId: order.id, initialOrder: order),
            ),
          ),
          child: Container(
            height: 50,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: OvanieColors.border),
            ),
            child: const Row(
              children: [
                Icon(Icons.description_outlined, color: Color(0xFF0759C7)),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Voir ma commande',
                    style: TextStyle(
                      color: Color(0xFF0759C7),
                      fontWeight: FontWeight.w900,
                      fontSize: 13,
                    ),
                  ),
                ),
                Icon(Icons.chevron_right_rounded, color: OvanieColors.navy),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: SizedBox(
                height: 52,
                child: FilledButton.icon(
                  onPressed: downloading ? null : onDownload,
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: downloading
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Icon(Icons.file_download_outlined),
                  label: const Text(
                    'Télécharger PDF',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: SizedBox(
                height: 52,
                child: OutlinedButton.icon(
                  onPressed: downloading ? null : onShare,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: OvanieColors.orange,
                    side: const BorderSide(color: OvanieColors.orange, width: 1.3),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: const Icon(Icons.ios_share_outlined),
                  label: const Text('Partager', style: TextStyle(fontWeight: FontWeight.w900)),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  static String _paymentLabel(MobileOrder order) {
    final label = order.paymentMethodLabel.trim();
    if (label.isNotEmpty) return label;
    final provider = order.paymentProvider.trim();
    if (provider.isNotEmpty) return _operatorName(provider);
    return order.paymentMethod.trim().isEmpty ? 'Paiement' : order.paymentMethod;
  }

  static String _operatorName(String code) {
    switch (code.toLowerCase()) {
      case 'wave':
        return 'Wave';
      case 'orange':
        return 'Orange Money';
      case 'mtn':
        return 'MTN MoMo';
      case 'moov':
        return 'Moov Money';
      case 'card':
        return 'Carte bancaire';
      default:
        return code;
    }
  }

  static Widget? _paymentLogo(MobileOrder order) {
    final haystack = '${order.paymentProvider} ${order.paymentMethodLabel} ${order.paymentMethod}'.toLowerCase();
    String? asset;
    if (haystack.contains('wave')) asset = 'assets/images/operators/wave.png';
    if (haystack.contains('orange')) asset = 'assets/images/operators/orange.png';
    if (haystack.contains('mtn')) asset = 'assets/images/operators/mtn.png';
    if (haystack.contains('moov')) asset = 'assets/images/operators/moov.png';
    if (asset == null) return null;
    return SizedBox(width: 38, height: 38, child: Image.asset(asset, fit: BoxFit.contain));
  }
}

class _InvoiceIdentity extends StatelessWidget {
  final String invoiceNumber;
  final String orderNumber;
  final DateTime? emittedAt;
  final bool paid;

  const _InvoiceIdentity({
    required this.invoiceNumber,
    required this.orderNumber,
    required this.emittedAt,
    required this.paid,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 54,
          height: 54,
          decoration: const BoxDecoration(
            color: Color(0xFFF1F5FF),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.receipt_long_outlined, color: Color(0xFF165DFF), size: 28),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Facture n°  $invoiceNumber',
                style: const TextStyle(
                  color: OvanieColors.navy,
                  fontWeight: FontWeight.w900,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 7),
              Text.rich(
                TextSpan(
                  style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5),
                  children: [
                    const TextSpan(text: 'Liée à la commande  '),
                    TextSpan(
                      text: orderNumber,
                      style: const TextStyle(color: Color(0xFF0759C7)),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 7),
              Text('Émise le  ${_formatDate(emittedAt)}', style: const TextStyle(fontSize: 11.5, color: OvanieColors.navy)),
              const SizedBox(height: 7),
              Row(
                children: [
                  const Text('Statut', style: TextStyle(fontSize: 11.5, color: OvanieColors.navy)),
                  const SizedBox(width: 12),
                  _PaidBadge(paid: paid),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InvoiceTotal extends StatelessWidget {
  final double total;
  const _InvoiceTotal({required this.total});

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Montant total', style: TextStyle(color: OvanieColors.navy, fontSize: 11.5)),
          const SizedBox(height: 10),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              formatFcfa(total),
              style: const TextStyle(
                color: OvanieColors.orange,
                fontSize: 25,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      );
}

class _PartyBlock extends StatelessWidget {
  final IconData icon;
  final String title;
  final List<String> lines;

  const _PartyBlock({required this.icon, required this.title, required this.lines});

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: OvanieColors.navy, size: 20),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.5, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                ...lines.map(
                  (line) => Padding(
                    padding: const EdgeInsets.only(bottom: 5),
                    child: Text(
                      line,
                      style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.3),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      );
}

class _InvoiceTableHeader extends StatelessWidget {
  const _InvoiceTableHeader();

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.only(bottom: 8),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: OvanieColors.border)),
        ),
        child: const Row(
          children: [
            Expanded(flex: 6, child: Text('Article', style: _InvoiceText.tableHeader)),
            Expanded(child: Text('Qté', textAlign: TextAlign.center, style: _InvoiceText.tableHeader)),
            Expanded(flex: 2, child: Text('PU', textAlign: TextAlign.right, style: _InvoiceText.tableHeader)),
            Expanded(flex: 2, child: Text('Total', textAlign: TextAlign.right, style: _InvoiceText.tableHeader)),
          ],
        ),
      );
}

class _InvoiceItemRow extends StatelessWidget {
  final MobileOrderItem item;
  const _InvoiceItemRow({required this.item});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: OvanieColors.border)),
        ),
        child: Row(
          children: [
            Expanded(
              flex: 6,
              child: Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(7),
                      border: Border.all(color: OvanieColors.border),
                    ),
                    child: item.imageUrl.trim().isEmpty
                        ? const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted, size: 22)
                        : Image.network(
                            item.imageUrl,
                            fit: BoxFit.contain,
                            errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined, color: OvanieColors.muted, size: 22),
                          ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.productName,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: OvanieColors.navy, fontSize: 11.8, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 2),
                        const Text('Produit OVANIE', style: TextStyle(color: OvanieColors.muted, fontSize: 9.7)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Text('${item.quantity}', textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5)),
            ),
            Expanded(
              flex: 2,
              child: Text(formatFcfa(item.unitPrice), textAlign: TextAlign.right, style: const TextStyle(color: OvanieColors.navy, fontSize: 10.7)),
            ),
            Expanded(
              flex: 2,
              child: Text(
                formatFcfa(item.subtotal),
                textAlign: TextAlign.right,
                style: const TextStyle(color: OvanieColors.orange, fontSize: 10.7, fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      );
}

class _MoneyRow extends StatelessWidget {
  final String label;
  final double value;
  final bool accent;

  const _MoneyRow({required this.label, required this.value, this.accent = false});

  @override
  Widget build(BuildContext context) => Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(color: accent ? OvanieColors.orange : OvanieColors.navy, fontSize: 11.5),
            ),
          ),
          Text(
            formatFcfa(value),
            style: TextStyle(color: accent ? OvanieColors.orange : OvanieColors.navy, fontSize: 11.5),
          ),
        ],
      );
}

class _PaymentField extends StatelessWidget {
  final String label;
  final String value;
  final Widget? leading;
  final bool badge;

  const _PaymentField({required this.label, required this.value, this.leading, this.badge = false});

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: OvanieColors.muted, fontSize: 9.8)),
          const SizedBox(height: 5),
          Row(
            children: [
              if (leading != null) ...[leading!, const SizedBox(width: 8)],
              Flexible(
                child: badge
                    ? Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE9F8EC),
                          borderRadius: BorderRadius.circular(7),
                          border: Border.all(color: const Color(0xFFBCE6C2)),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.check_circle_outline_rounded, color: Color(0xFF159447), size: 17),
                            const SizedBox(width: 5),
                            Flexible(
                              child: Text(
                                value,
                                style: const TextStyle(color: Color(0xFF13843E), fontSize: 10.5, fontWeight: FontWeight.w800),
                              ),
                            ),
                          ],
                        ),
                      )
                    : Text(
                        value,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: OvanieColors.navy, fontSize: 10.8, fontWeight: FontWeight.w800),
                      ),
              ),
            ],
          ),
        ],
      );
}

class _PaidBadge extends StatelessWidget {
  final bool paid;
  const _PaidBadge({required this.paid});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        decoration: BoxDecoration(
          color: paid ? const Color(0xFFEAF8EB) : const Color(0xFFFFF1EE),
          borderRadius: BorderRadius.circular(7),
          border: Border.all(color: paid ? const Color(0xFFBFE7C4) : const Color(0xFFFFC8BC)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              paid ? Icons.check_circle_outline_rounded : Icons.schedule_rounded,
              size: 16,
              color: paid ? const Color(0xFF13933E) : OvanieColors.orange,
            ),
            const SizedBox(width: 5),
            Text(
              paid ? 'Payée' : 'En attente',
              style: TextStyle(
                color: paid ? const Color(0xFF13843E) : OvanieColors.orange,
                fontWeight: FontWeight.w800,
                fontSize: 10.8,
              ),
            ),
          ],
        ),
      );
}

class _TaxBadge extends StatelessWidget {
  const _TaxBadge();
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        decoration: BoxDecoration(
          color: const Color(0xFFF0F5FF),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: const Color(0xFFD7E4FF)),
        ),
        child: const Text(
          'TVA / taxes incluses',
          style: TextStyle(color: Color(0xFF1559E6), fontSize: 9.8),
        ),
      );
}

class _InvoiceCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;

  const _InvoiceCard({
    required this.child,
    this.padding = const EdgeInsets.all(14),
  });

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: padding,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(11),
          border: Border.all(color: const Color(0xFFDDE4ED)),
          boxShadow: const [
            BoxShadow(color: Color(0x08071B48), blurRadius: 10, offset: Offset(0, 3)),
          ],
        ),
        child: child,
      );
}

class _InvoiceText {
  static const sectionTitle = TextStyle(
    color: OvanieColors.navy,
    fontSize: 15,
    fontWeight: FontWeight.w900,
  );
  static const tableHeader = TextStyle(
    color: OvanieColors.navy,
    fontSize: 10.5,
    fontWeight: FontWeight.w700,
  );
}

class _InvoiceError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _InvoiceError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.receipt_long_outlined, size: 52, color: OvanieColors.muted),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
            ],
          ),
        ),
      );
}

String _formatDate(DateTime? date) {
  if (date == null) return '—';
  const months = [
    'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
    'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

String _formatDateTime(DateTime? date) {
  if (date == null) return '—';
  final minute = date.minute.toString().padLeft(2, '0');
  return '${_formatDate(date)} à ${date.hour.toString().padLeft(2, '0')}:$minute';
}
