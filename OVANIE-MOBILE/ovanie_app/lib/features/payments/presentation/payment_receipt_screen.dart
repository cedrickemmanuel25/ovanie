import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/utils/formatters.dart';
import '../../orders/presentation/invoice_screen.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../domain/payment_models.dart';
import 'payment_ui.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);
const _pageBackground = Color(0xFFF2F4F7);

/// Reçu d'un paiement : montant, statut, moyen de paiement et référence.
class PaymentReceiptScreen extends StatelessWidget {
  const PaymentReceiptScreen({
    super.key,
    required this.payment,
    required this.statusLabel,
    required this.statusColor,
    required this.success,
  });

  final ClientPaymentHistoryItem payment;
  final String statusLabel;
  final Color statusColor;
  final bool success;

  void _copyReference(BuildContext context) {
    Clipboard.setData(ClipboardData(text: payment.reference));
    showOvanieToast(context, 'Référence copiée.');
  }

  @override
  Widget build(BuildContext context) {
    final rows = <(String, String)>[
      ('Commande', payment.orderNumber.isEmpty ? '—' : payment.orderNumber),
      ('Moyen de paiement', payment.methodLabel),
      ('Date', paymentDateTime(payment.paidAt ?? payment.createdAt, separator: ' · ')),
      ('Référence', payment.reference.isEmpty ? '—' : payment.reference),
    ];
    return Scaffold(
      backgroundColor: _pageBackground,
      appBar: AppBar(
        backgroundColor: _pageBackground,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: Text(
          success ? 'Reçu de paiement' : 'Détail du paiement',
          style: const TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 16, 18, 24),
        children: [
          PhysicalShape(
            clipper: const _TicketClipper(),
            color: Colors.white,
            elevation: 2,
            shadowColor: const Color(0x260A1B3D),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 34, 16, 26),
              child: Column(
                children: [
                  Container(
                    width: 108,
                    height: 108,
                    decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.12), shape: BoxShape.circle),
                    alignment: Alignment.center,
                    child: Container(
                      width: 62,
                      height: 62,
                      decoration: BoxDecoration(color: statusColor, shape: BoxShape.circle),
                      child: Icon(
                        success ? Icons.check_rounded : Icons.receipt_long_rounded,
                        color: Colors.white,
                        size: 36,
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      formatFcfa(payment.amount),
                      style: const TextStyle(
                        color: _navy,
                        fontSize: 34,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -1,
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 5),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      statusLabel,
                      style: TextStyle(color: statusColor, fontSize: 14.5, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(height: 22),
                  const _DashedLine(),
                  const SizedBox(height: 18),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Détail du paiement',
                      style: TextStyle(color: _navy, fontSize: 17, fontWeight: FontWeight.w800),
                    ),
                  ),
                  const SizedBox(height: 6),
                  for (var i = 0; i < rows.length; i++) ...[
                    if (i > 0) const Divider(height: 1, color: _line),
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 11),
                      child: Row(
                        children: [
                          Text(rows[i].$1, style: const TextStyle(color: _muted, fontSize: 14)),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Text(
                              rows[i].$2,
                              textAlign: TextAlign.right,
                              style: const TextStyle(color: _navy, fontSize: 14),
                            ),
                          ),
                          if (rows[i].$1 == 'Référence' && payment.reference.isNotEmpty) ...[
                            const SizedBox(width: 6),
                            InkResponse(
                              onTap: () => _copyReference(context),
                              radius: 20,
                              child: const Padding(
                                padding: EdgeInsets.all(2),
                                child: Icon(Icons.copy_rounded, color: Color(0xFF4A586E), size: 20),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          if (payment.orderId > 0) ...[
            const SizedBox(height: 26),
            if (success) ...[
              SizedBox(
                height: 50,
                child: FilledButton(
                  onPressed: () => Navigator.of(
                    context,
                  ).push(MaterialPageRoute<void>(builder: (_) => InvoiceScreen(orderId: payment.orderId))),
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: const Text('Voir la facture', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                ),
              ),
              const SizedBox(height: 12),
            ],
            SizedBox(
              height: 50,
              child: OutlinedButton(
                onPressed: () => Navigator.of(
                  context,
                ).push(MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: payment.orderId))),
                style: OutlinedButton.styleFrom(
                  foregroundColor: _navy,
                  backgroundColor: Colors.white,
                  side: const BorderSide(color: _navy, width: 1.4),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Voir la commande', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Ticket aux bords haut et bas festonnés.
class _TicketClipper extends CustomClipper<Path> {
  const _TicketClipper();

  @override
  Path getClip(Size size) {
    const radius = 14.0;
    const notch = 9.0;
    const count = 10;
    final step = size.width / count;
    final path = Path()..moveTo(0, radius);
    path.quadraticBezierTo(0, 0, radius, 0);
    for (var i = 0; i < count; i++) {
      final center = step * (i + 0.5);
      path
        ..lineTo(center - notch, 0)
        ..arcToPoint(Offset(center + notch, 0), radius: const Radius.circular(notch), clockwise: false);
    }
    path
      ..lineTo(size.width - radius, 0)
      ..quadraticBezierTo(size.width, 0, size.width, radius)
      ..lineTo(size.width, size.height - radius)
      ..quadraticBezierTo(size.width, size.height, size.width - radius, size.height);
    for (var i = count - 1; i >= 0; i--) {
      final center = step * (i + 0.5);
      path
        ..lineTo(center + notch, size.height)
        ..arcToPoint(Offset(center - notch, size.height), radius: const Radius.circular(notch), clockwise: false);
    }
    path
      ..lineTo(radius, size.height)
      ..quadraticBezierTo(0, size.height, 0, size.height - radius)
      ..close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

class _DashedLine extends StatelessWidget {
  const _DashedLine();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final count = (constraints.maxWidth / 9).floor();
        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(count, (_) => Container(width: 5, height: 1.2, color: const Color(0xFFCBD3DE))),
        );
      },
    );
  }
}
