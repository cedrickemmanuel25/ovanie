import 'package:flutter/material.dart';

import '../orders/order_ui.dart';

const financeGreen = Color(0xFF168A2B);
const financePurple = Color(0xFF6F41DB);

String payoutStatusLabel(Object? raw) {
  switch ('${raw ?? ''}'.trim().toLowerCase()) {
    case 'paid':
      return 'Effectué';
    case 'processing':
      return 'En traitement';
    case 'approved':
      return 'En attente';
    case 'pending':
      return 'Planifié';
    case 'waiting_payment':
      return 'En attente paiement';
    case 'waiting_reception':
      return 'En attente réception';
    case 'failed':
      return 'Échoué';
    case 'blocked':
      // Dans l'immense majorité des cas, "bloqué" signifie simplement que la
      // commande n'est pas encore livrée — pas un incident. "Bloqué" faisait
      // craindre à tort au vendeur que son argent ait un problème.
      return 'En attente de livraison';
    case 'cancelled':
      return 'Annulé';
    default:
      final text = '${raw ?? ''}'.trim();
      return text.isEmpty ? 'Planifié' : text;
  }
}

Color payoutStatusColor(Object? raw) {
  switch ('${raw ?? ''}'.trim().toLowerCase()) {
    case 'paid':
      return financeGreen;
    case 'processing':
      return financePurple;
    case 'approved':
    case 'waiting_payment':
    case 'waiting_reception':
      return orderOrange;
    case 'pending':
      return orderBlue;
    case 'failed':
    case 'blocked':
    case 'cancelled':
      return const Color(0xFFD92D20);
    default:
      return orderBlue;
  }
}

String payoutMethodAsset(Object? method) {
  switch ('${method ?? ''}'.trim().toLowerCase()) {
    case 'orange':
    case 'orange_money':
      return 'assets/images/operators/orange.png';
    case 'mtn':
    case 'mtn_money':
      return 'assets/images/operators/mtn.png';
    case 'moov':
    case 'moov_money':
      return 'assets/images/operators/moov.png';
    case 'wave':
      return 'assets/images/operators/wave.png';
    default:
      return '';
  }
}

class PayoutStatusPill extends StatelessWidget {
  const PayoutStatusPill({
    super.key,
    required this.status,
    this.label,
    this.compact = false,
  });
  final Object? status;
  final String? label;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final color = payoutStatusColor(status);
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 12 : 16,
        vertical: compact ? 7 : 8,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .11),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        label ?? payoutStatusLabel(status),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color,
          fontSize: compact ? 12 : 13.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

/// Badge de statut affiché dans l'en-tête (ex. "Versé · le 15 mars 2025").
class PayoutHeaderBadge extends StatelessWidget {
  const PayoutHeaderBadge({super.key, required this.status, this.dateLabel});
  final Object? status;
  final String? dateLabel;

  @override
  Widget build(BuildContext context) {
    final color = payoutStatusColor(status);
    final paid = '${status ?? ''}'.toLowerCase() == 'paid';
    final icon = paid
        ? Icons.check_circle
        : ['failed', 'blocked', 'cancelled'].contains('${status ?? ''}'.toLowerCase())
            ? Icons.error
            : Icons.schedule;
    return Container(
      constraints: const BoxConstraints(maxWidth: 128),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: Colors.white, size: 15),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  payoutStatusLabel(status),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
          if ((dateLabel ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              dateLabel!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: 11, height: 1.1),
            ),
          ],
        ],
      ),
    );
  }
}

/// Puce de référence compacte affichée dans l'en-tête (ex. suivi de demande).
class HeaderRefBadge extends StatelessWidget {
  const HeaderRefBadge({super.key, required this.reference, this.dateLabel});
  final String reference;
  final String? dateLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 128),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFF0F559F).withValues(alpha: .72),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            reference,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900),
          ),
          if ((dateLabel ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              dateLabel!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Color(0xFFD9E8FA), fontSize: 10.5),
            ),
          ],
        ],
      ),
    );
  }
}

/// Carte flottante en pied d'en-tête (4 indicateurs en une ligne).
class FinanceHeaderStats extends StatelessWidget {
  const FinanceHeaderStats({super.key, required this.items});
  final List<(IconData, Color, String, String)> items;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [BoxShadow(color: Color(0x1A02173D), blurRadius: 18, offset: Offset(0, 8))],
      ),
      child: Row(
        children: [
          for (var i = 0; i < items.length; i++) ...[
            if (i != 0) const SizedBox(width: 6),
            Expanded(child: _statCell(items[i])),
          ],
        ],
      ),
    );
  }

  Widget _statCell((IconData, Color, String, String) item) {
    final (icon, color, label, value) = item;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 30,
          height: 30,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: color.withValues(alpha: .11), shape: BoxShape.circle),
          child: Icon(icon, color: color, size: 16),
        ),
        const SizedBox(height: 8),
        Text(
          label,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: orderMuted, fontSize: 10, height: 1.15),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: orderText, fontSize: 13, fontWeight: FontWeight.w800, height: 1.15),
        ),
      ],
    );
  }
}

/// Chip de filtre à contour, sans remplissage (fidèle à la maquette).
class PayoutFilterChip extends StatelessWidget {
  const PayoutFilterChip({super.key, required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(99),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(99),
          border: Border.all(color: selected ? orderOrange : orderBorder, width: selected ? 1.4 : 1),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? orderOrange : orderMuted,
            fontSize: 13,
            fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

/// Icône/logo de moyen de paiement : logo opérateur réel si disponible,
/// sinon icône bancaire générique (virement).
class PayoutMethodIcon extends StatelessWidget {
  const PayoutMethodIcon({
    super.key,
    required this.method,
    this.size = 60,
    this.color,
  });
  final Object? method;
  final double size;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final asset = payoutMethodAsset(method);
    final bg = color ?? orderBlue;
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: asset.isEmpty ? bg.withValues(alpha: .10) : Colors.white,
        borderRadius: BorderRadius.circular(size * .22),
        border: asset.isEmpty ? null : Border.all(color: orderBorder),
      ),
      child: asset.isEmpty
          ? Icon(Icons.account_balance_outlined, color: bg, size: size * .45)
          : Padding(
              padding: EdgeInsets.all(size * .16),
              child: Image.asset(asset, fit: BoxFit.contain),
            ),
    );
  }
}
