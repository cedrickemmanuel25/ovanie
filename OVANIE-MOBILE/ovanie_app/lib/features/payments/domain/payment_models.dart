import '../../../core/utils/text_cleaner.dart';

DateTime? _date(dynamic value) {
  final raw = '${value ?? ''}'.trim();
  return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
}

double _amount(dynamic value) => value is num ? value.toDouble() : double.tryParse('${value ?? ''}') ?? 0;

class ClientPaymentHistoryItem {
  final int id;
  final int orderId;
  final String orderNumber;
  final String reference;
  final String method;
  final String operator;
  final double amount;
  final String status;
  final String type;
  final DateTime? paidAt;
  final DateTime? createdAt;

  const ClientPaymentHistoryItem({
    required this.id,
    required this.orderId,
    required this.orderNumber,
    required this.reference,
    required this.method,
    required this.operator,
    required this.amount,
    required this.status,
    required this.type,
    required this.paidAt,
    required this.createdAt,
  });

  String get statusLabel => switch (status.toLowerCase()) {
        'paid' || 'commission_paid' || 'escrow_held' => 'Payé',
        'pending' || 'initiated' => 'En attente',
        'failed' => 'Échoué',
        'cancelled' || 'canceled' => 'Annulé',
        'refunded' => 'Remboursé',
        _ => cleanOvanieText(status.isEmpty ? 'Mise à jour en cours' : status),
      };

  String get methodLabel {
    final op = operator.toLowerCase();
    if (op.contains('orange')) return 'Orange Money';
    if (op.contains('mtn')) return 'MTN MoMo';
    if (op.contains('wave')) return 'Wave';
    if (op.contains('moov')) return 'Moov Money';
    if (op.contains('card') || op.contains('carte')) return 'Carte bancaire';
    if (method.toLowerCase().contains('cash')) return 'Paiement à la livraison';
    if (method.toLowerCase().contains('paydunya')) return 'Paiement en ligne';
    return cleanOvanieText(method.isEmpty ? 'Paiement OVANIE' : method);
  }

  factory ClientPaymentHistoryItem.fromJson(Map<String, dynamic> json) => ClientPaymentHistoryItem(
        id: int.tryParse('${json['id'] ?? 0}') ?? 0,
        orderId: int.tryParse('${json['order_id'] ?? 0}') ?? 0,
        orderNumber: cleanOvanieText('${json['order_number'] ?? ''}'),
        reference: cleanOvanieText('${json['reference'] ?? ''}'),
        method: '${json['method'] ?? ''}'.trim(),
        operator: '${json['operator'] ?? ''}'.trim(),
        amount: _amount(json['amount']),
        status: '${json['status'] ?? ''}'.trim().toLowerCase(),
        type: '${json['type'] ?? ''}'.trim(),
        paidAt: _date(json['paid_at']),
        createdAt: _date(json['created_at']),
      );
}

class ClientPaymentHistoryPage {
  final List<ClientPaymentHistoryItem> items;
  final int currentPage;
  final int lastPage;
  final int total;

  const ClientPaymentHistoryPage({required this.items, required this.currentPage, required this.lastPage, required this.total});
}
