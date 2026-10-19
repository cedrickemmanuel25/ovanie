import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:open_filex/open_filex.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../payments/presentation/payment_ui.dart';
import '../data/orders_repository.dart';
import '../domain/order_model.dart';
import 'order_detail_screen.dart';

class InvoiceScreen extends StatefulWidget {
  final int orderId;
  final MobileOrder? initialOrder;

  const InvoiceScreen({super.key, required this.orderId, this.initialOrder});

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
      if (share) showOvanieToast(context, 'Le PDF est ouvert. Utilisez le bouton Partager de votre lecteur PDF.');
    } catch (error) {
      if (!mounted) return;
      showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: const Color(0xFFF4F6FA),
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
                child: Row(
                  children: [
                    _RoundButton(icon: Icons.chevron_left_rounded, onTap: () => Navigator.of(context).maybePop()),
                    const Expanded(
                      child: Text(
                        'Facture',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: payNavy, fontSize: 24, fontWeight: FontWeight.w800),
                      ),
                    ),
                    _RoundButton(
                      icon: Icons.ios_share_rounded,
                      onTap: order == null || _downloading ? null : () => _download(share: true),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
                    : order == null
                    ? _InvoiceError(message: _error ?? 'Cette facture est indisponible.', onRetry: _load)
                    : RefreshIndicator(
                        color: OvanieColors.orange,
                        onRefresh: _load,
                        child: _InvoiceBody(order: order),
                      ),
              ),
              if (order != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
                  child: Row(
                    children: [
                      Expanded(
                        child: PaymentButton(
                          label: 'Voir ma commande',
                          outlined: true,
                          height: 56,
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => OrderDetailScreen(orderId: order.id, initialOrder: order),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: SizedBox(
                          height: 56,
                          child: FilledButton.icon(
                            onPressed: _downloading ? null : () => _download(),
                            style: FilledButton.styleFrom(
                              elevation: 0,
                              backgroundColor: OvanieColors.orange,
                              disabledBackgroundColor: OvanieColors.orange.withValues(alpha: .6),
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            icon: _downloading
                                ? const SizedBox.square(
                                    dimension: 20,
                                    child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                                  )
                                : const Icon(Icons.file_download_outlined, size: 26),
                            label: const FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                'Télécharger PDF',
                                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
                              ),
                            ),
                          ),
                        ),
                      ),
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

class _RoundButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;

  const _RoundButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: const CircleBorder(),
    elevation: 2,
    shadowColor: const Color(0x26000000),
    child: InkWell(
      customBorder: const CircleBorder(),
      onTap: onTap,
      child: SizedBox(width: 52, height: 52, child: Icon(icon, color: payNavy, size: 28)),
    ),
  );
}

class _InvoiceBody extends StatelessWidget {
  final MobileOrder order;

  const _InvoiceBody({required this.order});

  @override
  Widget build(BuildContext context) {
    final session = SessionStore.instance;
    final invoiceNumber = order.invoiceNumber.trim().isNotEmpty
        ? order.invoiceNumber.trim()
        : 'FAC-${order.orderNumber}';
    final billedName = (session.name ?? '').trim().isNotEmpty
        ? session.name!.trim()
        : (order.deliveryRecipientName.trim().isNotEmpty ? order.deliveryRecipientName.trim() : 'Client OVANIE');
    final phone = formatCiPhoneDisplay((session.phone ?? '').trim().isNotEmpty ? session.phone! : order.deliveryPhone);
    final email = (session.email ?? '').trim();
    final addressLines = <String>[if (order.address.trim().isNotEmpty) order.address.trim()];
    final area = [order.quartier, order.commune]
        .map((part) => part.trim())
        .where((part) => part.isNotEmpty && !order.address.toLowerCase().contains(part.toLowerCase()))
        .join(', ');
    if (area.isNotEmpty) addressLines.add(area);
    addressLines.add('${order.city.trim().isEmpty ? 'Abidjan' : order.city.trim()}, Côte d’Ivoire');
    final paid = order.isPaymentConfirmed;
    final discount = order.discount + order.loyaltyDiscount;
    final code = operatorCodeOf('${order.paymentProvider} ${order.paymentMethodLabel}');

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 6, 14, 14),
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Image.asset('assets/images/ovanie_logo.png', height: 48, fit: BoxFit.contain),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        'Facture n° $invoiceNumber',
                        style: const TextStyle(color: payNavy, fontSize: 14, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        'Liée à la commande ${order.orderNumber}',
                        style: const TextStyle(color: payMuted, fontSize: 13),
                      ),
                      Text(
                        'Émise le ${paymentShortDate(order.createdAt)}',
                        style: const TextStyle(color: payMuted, fontSize: 13),
                      ),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                        decoration: BoxDecoration(
                          color: paid ? payGreenSoft : const Color(0xFFFFEDE2),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Text(
                          paid ? 'Payée' : 'À régler',
                          style: TextStyle(
                            color: paid ? payGreenDark : const Color(0xFFC2410C),
                            fontSize: 13.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 14),
                decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(12)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Montant total', style: TextStyle(color: payMuted, fontSize: 14.5)),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(
                        formatFcfa(order.total),
                        style: const TextStyle(color: Color(0xFF0B1220), fontSize: 38, fontWeight: FontWeight.w900),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: _PartyCard(
                        title: 'Facturé à',
                        children: [
                          Text(
                            billedName,
                            style: const TextStyle(color: payNavy, fontSize: 14.5, fontWeight: FontWeight.w700),
                          ),
                          if (phone.isNotEmpty) _IconLine(icon: Icons.call_outlined, text: phone),
                          if (email.isNotEmpty) _IconLine(icon: Icons.mail_outline_rounded, text: email),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _PartyCard(
                        title: 'Adresse de livraison',
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Padding(
                                padding: EdgeInsets.only(top: 2),
                                child: Icon(Icons.location_on_rounded, color: Color(0xFF4A556E), size: 18),
                              ),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text(
                                  addressLines.join('\n'),
                                  style: const TextStyle(color: payNavy, fontSize: 13, height: 1.45),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const Divider(height: 30, color: payBorder),
              const Text(
                'Détail de la facture',
                style: TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 12),
              Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: payBorder),
                ),
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    Container(
                      color: paySoft,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      child: const Row(
                        children: [
                          Expanded(child: Text('Article', style: _InvoiceText.header)),
                          SizedBox(
                            width: 44,
                            child: Text('Qté', textAlign: TextAlign.center, style: _InvoiceText.header),
                          ),
                          SizedBox(
                            width: 104,
                            child: Text('Total', textAlign: TextAlign.right, style: _InvoiceText.header),
                          ),
                        ],
                      ),
                    ),
                    for (var i = 0; i < order.items.length; i++)
                      _InvoiceItemRow(item: order.items[i], last: i == order.items.length - 1),
                  ],
                ),
              ),
              const Divider(height: 34, color: payBorder),
              const Text(
                'Récapitulatif',
                style: TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerRight,
                child: FractionallySizedBox(
                  widthFactor: .66,
                  child: Column(
                    children: [
                      _MoneyLine(label: 'Sous-total', value: formatFcfa(order.subtotal)),
                      _MoneyLine(label: 'Livraison', value: formatFcfa(order.deliveryFee)),
                      if (discount > 0) _MoneyLine(label: 'Remise', value: '- ${formatFcfa(discount)}'),
                      const Divider(height: 18, color: payBorder),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              paid ? 'Total payé' : 'Total à payer',
                              style: const TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w800),
                            ),
                          ),
                          Text(
                            formatFcfa(order.total),
                            style: const TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w900),
                          ),
                        ],
                      ),
                      const Align(
                        alignment: Alignment.centerRight,
                        child: Text('TVA / taxes incluses', style: TextStyle(color: payMuted, fontSize: 12.5)),
                      ),
                    ],
                  ),
                ),
              ),
              const Divider(height: 34, color: payBorder),
              const Text(
                'Paiement',
                style: TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 10),
              _PaymentLine(
                label: 'Méthode',
                child: Row(
                  children: [
                    if (code.isNotEmpty && code != 'card') ...[
                      PaymentOperatorLogo(code: code, size: 26),
                      const SizedBox(width: 10),
                    ],
                    Expanded(
                      child: Text(
                        code.isNotEmpty ? operatorLabelOf(code) : _methodLabel(order),
                        style: const TextStyle(color: payNavy, fontSize: 14.5),
                      ),
                    ),
                  ],
                ),
              ),
              _PaymentLine(
                label: 'Référence',
                child: Text(
                  order.paymentReference.trim().isEmpty ? '—' : order.paymentReference.trim(),
                  style: const TextStyle(color: payNavy, fontSize: 14.5),
                ),
              ),
              _PaymentLine(
                label: 'Date',
                child: Text(
                  paymentDateTime(order.paymentAttemptCreatedAt ?? order.createdAt),
                  style: const TextStyle(color: payNavy, fontSize: 14.5),
                ),
              ),
              _PaymentLine(
                label: 'Statut',
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                    decoration: BoxDecoration(
                      color: paid ? payGreenSoft : const Color(0xFFFFEDE2),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Text(
                      paid ? 'Transaction réussie' : _pendingLabel(order),
                      style: TextStyle(
                        color: paid ? payGreenDark : const Color(0xFFC2410C),
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(10)),
                child: const Text(
                  'Cette facture est générée par OVANIE et sert de justificatif d’achat.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: payMuted, fontSize: 13),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  static String _methodLabel(MobileOrder order) {
    final text = '${order.paymentMethod} ${order.paymentMethodLabel}'.toLowerCase();
    if (text.contains('cash') || text.contains('livraison')) return 'Paiement à la livraison';
    final label = order.paymentMethodLabel.trim();
    return label.isEmpty ? 'Paiement en ligne' : label;
  }

  static String _pendingLabel(MobileOrder order) {
    final text = '${order.paymentMethod} ${order.paymentMethodLabel}'.toLowerCase();
    if (text.contains('cash') || text.contains('livraison')) return 'À régler à la livraison';
    return order.paymentStatusLabel.trim().isEmpty ? 'En attente' : order.paymentStatusLabel.trim();
  }
}

class _PartyCard extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const _PartyCard({required this.title, required this.children});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: payBorder),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(color: payNavy, fontSize: 15, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 10),
        ...children,
      ],
    ),
  );
}

class _IconLine extends StatelessWidget {
  final IconData icon;
  final String text;

  const _IconLine({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: Row(
      children: [
        Icon(icon, color: const Color(0xFF4A556E), size: 18),
        const SizedBox(width: 8),
        Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(text, maxLines: 1, style: const TextStyle(color: payNavy, fontSize: 13)),
          ),
        ),
      ],
    ),
  );
}

class _InvoiceItemRow extends StatelessWidget {
  final MobileOrderItem item;
  final bool last;

  const _InvoiceItemRow({required this.item, required this.last});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(12, 10, 14, 10),
    decoration: BoxDecoration(
      border: last ? null : const Border(bottom: BorderSide(color: payBorder)),
    ),
    child: Row(
      children: [
        OrderItemThumbnail(url: item.imageUrl, width: 54, height: 54),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            [item.productName, item.variantLabel].where((part) => part.trim().isNotEmpty).join('\n'),
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: payNavy, fontSize: 13.5, height: 1.3),
          ),
        ),
        SizedBox(
          width: 44,
          child: Text(
            '${item.quantity}',
            textAlign: TextAlign.center,
            style: const TextStyle(color: payNavy, fontSize: 14),
          ),
        ),
        SizedBox(
          width: 104,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  formatFcfa(item.subtotal),
                  style: const TextStyle(color: payNavy, fontSize: 14.5, fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(height: 2),
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  '${formatFcfa(item.unitPrice)} l’unité',
                  style: const TextStyle(color: payMuted, fontSize: 12),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _MoneyLine extends StatelessWidget {
  final String label;
  final String value;

  const _MoneyLine({required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      children: [
        Expanded(
          child: Text(label, style: const TextStyle(color: payMuted, fontSize: 14.5)),
        ),
        Text(value, style: const TextStyle(color: payNavy, fontSize: 14.5)),
      ],
    ),
  );
}

class _PaymentLine extends StatelessWidget {
  final String label;
  final Widget child;

  const _PaymentLine({required this.label, required this.child});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        SizedBox(
          width: 120,
          child: Text(label, style: const TextStyle(color: payMuted, fontSize: 14.5)),
        ),
        Expanded(child: child),
      ],
    ),
  );
}

class _InvoiceText {
  static const header = TextStyle(color: payMuted, fontSize: 13.5, fontWeight: FontWeight.w600);
}

class _InvoiceError extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _InvoiceError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 100,
            height: 100,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.receipt_long_outlined, color: Color(0xFF4A556E), size: 46),
          ),
          const SizedBox(height: 18),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: payNavy, fontSize: 16),
          ),
          const SizedBox(height: 18),
          PaymentButton(label: 'Réessayer', onPressed: onRetry),
        ],
      ),
    ),
  );
}
