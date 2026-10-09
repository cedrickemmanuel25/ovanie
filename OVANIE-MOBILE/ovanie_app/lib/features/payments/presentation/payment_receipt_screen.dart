import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/utils/formatters.dart';
import '../../orders/presentation/invoice_screen.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../domain/payment_models.dart';

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

  String _date(DateTime? value) {
    if (value == null) return '—';
    const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    String two(int n) => n.toString().padLeft(2, '0');
    final d = value.toLocal();
    return '${d.day} ${months[d.month - 1]} ${d.year} · ${two(d.hour)}:${two(d.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    final rows = <(String, String)>[
      ('Commande', payment.orderNumber.isEmpty ? '—' : '#${payment.orderNumber}'),
      ('Moyen de paiement', payment.methodLabel),
      ('Date', _date(payment.paidAt ?? payment.createdAt)),
      ('Référence', payment.reference.isEmpty ? '—' : payment.reference),
    ];
    return Scaffold(
      backgroundColor: OvanieColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: OvanieColors.navy,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: Text(success ? 'Reçu de paiement' : 'Détail du paiement', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(18, 24, 18, 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: OvanieColors.border),
            ),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 26,
                  backgroundColor: statusColor.withValues(alpha: .12),
                  child: Icon(success ? Icons.check_rounded : Icons.receipt_long_outlined, color: statusColor, size: 28),
                ),
                const SizedBox(height: 12),
                Text(
                  formatFcfa(payment.amount),
                  style: const TextStyle(color: OvanieColors.navy, fontSize: 28, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: statusColor.withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
                  child: Text(statusLabel, style: TextStyle(color: statusColor, fontSize: 13, fontWeight: FontWeight.w700)),
                ),
                const SizedBox(height: 18),
                const Divider(height: 1, color: OvanieColors.border),
                for (final row in rows)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(row.$1, style: const TextStyle(color: OvanieColors.muted, fontSize: 14)),
                        const SizedBox(width: 16),
                        Expanded(
                          child: GestureDetector(
                            onLongPress: row.$1 == 'Référence' && payment.reference.isNotEmpty
                                ? () {
                                    Clipboard.setData(ClipboardData(text: payment.reference));
                                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Référence copiée.')));
                                  }
                                : null,
                            child: Text(
                              row.$2,
                              textAlign: TextAlign.right,
                              style: const TextStyle(color: OvanieColors.navy, fontSize: 14, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          if (payment.orderId > 0) ...[
            const SizedBox(height: 16),
            if (success)
              SizedBox(
                height: 48,
                child: FilledButton.icon(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(builder: (_) => InvoiceScreen(orderId: payment.orderId)),
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: const Icon(Icons.description_outlined, size: 20),
                  label: const Text('Voir la facture', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                ),
              ),
            const SizedBox(height: 10),
            SizedBox(
              height: 48,
              child: OutlinedButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: payment.orderId)),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: OvanieColors.navy,
                  side: const BorderSide(color: OvanieColors.border),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Voir la commande', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
