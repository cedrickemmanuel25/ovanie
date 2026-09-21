import '../../../core/utils/text_cleaner.dart';

class MobileOrder {
  final int id;
  final String orderNumber;
  final String invoiceNumber;
  final String clientBucket;
  final String status;
  final String statusLabel;
  final String paymentStatus;
  final String paymentStatusLabel;
  final String paymentMethod;
  final String paymentMethodLabel;
  final String paymentReference;
  final String paymentProvider;
  final String paymentState;
  final String paymentAttemptStatus;
  final String paymentAttemptStatusLabel;
  final DateTime? paymentAttemptCreatedAt;
  final double settledAmount;
  final double outstandingAmount;
  final double refundedAmount;
  final double subtotal;
  final double deliveryFee;
  final double discount;
  final double loyaltyDiscount;
  final double total;
  final String deliveryStatus;
  final String deliveryStatusLabel;
  final String address;
  final String commune;
  final String quartier;
  final String city;
  final String deliveryRecipientName;
  final String deliveryPhone;
  final String deliveryNotes;
  final DateTime? estimatedMinDate;
  final DateTime? estimatedMaxDate;
  final DateTime? deliveredAt;
  final String receptionStatus;
  final MobileOrderActions actions;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final List<MobileOrderItem> items;

  const MobileOrder({
    required this.id,
    required this.orderNumber,
    required this.invoiceNumber,
    required this.clientBucket,
    required this.status,
    required this.statusLabel,
    required this.paymentStatus,
    required this.paymentStatusLabel,
    required this.paymentMethod,
    required this.paymentMethodLabel,
    required this.paymentReference,
    required this.paymentProvider,
    required this.paymentState,
    required this.paymentAttemptStatus,
    required this.paymentAttemptStatusLabel,
    required this.paymentAttemptCreatedAt,
    required this.settledAmount,
    required this.outstandingAmount,
    required this.refundedAmount,
    required this.subtotal,
    required this.deliveryFee,
    required this.discount,
    required this.loyaltyDiscount,
    required this.total,
    required this.deliveryStatus,
    required this.deliveryStatusLabel,
    required this.address,
    required this.commune,
    required this.quartier,
    required this.city,
    required this.deliveryRecipientName,
    required this.deliveryPhone,
    required this.deliveryNotes,
    required this.estimatedMinDate,
    required this.estimatedMaxDate,
    required this.deliveredAt,
    required this.receptionStatus,
    required this.actions,
    required this.createdAt,
    required this.updatedAt,
    required this.items,
  });

  bool get isPaymentConfirmed {
    final state = paymentState.toLowerCase().trim();
    final payment = paymentStatus.toLowerCase().trim();
    return state == 'paid' || const {
      'paid',
      'commission_paid',
      'escrow_held',
      'completed',
      'success',
      'successful',
    }.contains(payment);
  }

  bool get isPaymentPending => paymentState == 'pending';
  bool get isPaymentFailed => paymentState == 'failed';
  bool get isPaymentCancelled => paymentState == 'cancelled';


  bool get isClosed => const {'delivered', 'cancelled', 'returned'}.contains(clientBucket);

  String get destinationLabel {
    final values = [quartier, commune, city, address]
        .where((value) => value.trim().isNotEmpty)
        .toList(growable: false);
    return values.isEmpty ? '' : values.first;
  }

  factory MobileOrder.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> map(dynamic value) =>
        value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
    double amount(dynamic value) {
      if (value is num) return value.toDouble();
      return double.tryParse('${value ?? ''}') ?? 0;
    }

    DateTime? date(dynamic value) {
      final raw = value?.toString().trim() ?? '';
      return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
    }

    final payment = map(json['payment']);
    final financial = map(json['financial_summary']);
    final delivery = map(json['delivery']);
    final actions = map(json['actions']);
    final rawItems = json['items'] is List ? json['items'] as List : const [];

    return MobileOrder(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      orderNumber: cleanOvanieText((json['order_number'] ?? '').toString()),
      invoiceNumber: cleanOvanieText((json['invoice_number'] ?? '').toString()),
      clientBucket: (json['client_bucket'] ?? 'in_progress').toString().trim().toLowerCase(),
      status: (json['status'] ?? '').toString().trim().toLowerCase(),
      statusLabel: cleanOvanieText((json['status_label'] ?? 'Mise à jour en cours').toString()),
      paymentStatus: (payment['status'] ?? '').toString().trim().toLowerCase(),
      paymentStatusLabel: cleanOvanieText((payment['status_label'] ?? '').toString()),
      paymentMethod: (payment['method'] ?? '').toString().trim().toLowerCase(),
      paymentMethodLabel: cleanOvanieText((payment['method_label'] ?? '').toString()),
      paymentReference: cleanOvanieText((payment['reference'] ?? payment['payment_reference'] ?? payment['transaction_id'] ?? '').toString()),
      paymentProvider: cleanOvanieText((payment['operator'] ?? payment['provider'] ?? '').toString()),
      paymentState: (payment['state'] ?? payment['status'] ?? '').toString().trim().toLowerCase(),
      paymentAttemptStatus: (payment['attempt_status'] ?? '').toString().trim().toLowerCase(),
      paymentAttemptStatusLabel: cleanOvanieText((payment['attempt_status_label'] ?? '').toString()),
      paymentAttemptCreatedAt: date(payment['attempt_created_at']),
      settledAmount: amount(payment['settled_amount']),
      outstandingAmount: amount(payment['outstanding_amount']),
      refundedAmount: amount(payment['refunded_amount']),
      subtotal: amount(financial['subtotal']),
      deliveryFee: amount(financial['delivery']),
      discount: amount(financial['discount']),
      loyaltyDiscount: amount(financial['loyalty_discount']),
      total: amount(financial['total']),
      deliveryStatus: (delivery['status'] ?? '').toString().trim().toLowerCase(),
      deliveryStatusLabel: cleanOvanieText((delivery['status_label'] ?? '').toString()),
      address: cleanOvanieText((delivery['address'] ?? '').toString()),
      commune: cleanOvanieText((delivery['commune'] ?? '').toString()),
      quartier: cleanOvanieText((delivery['quartier'] ?? '').toString()),
      city: cleanOvanieText((delivery['city'] ?? '').toString()),
      deliveryRecipientName: cleanOvanieText((delivery['recipient_name'] ?? delivery['full_name'] ?? '').toString()),
      deliveryPhone: cleanOvanieText((delivery['phone'] ?? '').toString()),
      deliveryNotes: cleanOvanieText((delivery['notes'] ?? '').toString()),
      estimatedMinDate: date(delivery['estimated_min_date']),
      estimatedMaxDate: date(delivery['estimated_max_date']),
      deliveredAt: date(delivery['delivered_at']),
      receptionStatus: (json['reception_status'] ?? '').toString().trim().toLowerCase(),
      actions: MobileOrderActions.fromJson(actions),
      createdAt: date(json['created_at']),
      updatedAt: date(json['updated_at']),
      items: rawItems
          .whereType<Map>()
          .map((item) => MobileOrderItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
    );
  }
}

class MobileOrderActions {
  final bool canTrack;
  final bool canConfirmReception;
  final bool receptionConfirmed;
  final bool canDownloadInvoice;
  final bool canOpenReturn;
  final bool canCancel;
  final bool canReorder;
  final bool canResumePayment;

  const MobileOrderActions({
    required this.canTrack,
    required this.canConfirmReception,
    required this.receptionConfirmed,
    required this.canDownloadInvoice,
    required this.canOpenReturn,
    required this.canCancel,
    required this.canReorder,
    required this.canResumePayment,
  });

  const MobileOrderActions.empty()
      : canTrack = false,
        canConfirmReception = false,
        receptionConfirmed = false,
        canDownloadInvoice = false,
        canOpenReturn = false,
        canCancel = false,
        canReorder = false,
        canResumePayment = false;

  factory MobileOrderActions.fromJson(Map<String, dynamic> json) => MobileOrderActions(
        canTrack: json['can_track'] == true,
        canConfirmReception: json['can_confirm_reception'] == true,
        receptionConfirmed: json['reception_confirmed'] == true,
        canDownloadInvoice: json['can_download_invoice'] == true,
        canOpenReturn: json['can_open_return'] == true,
        canCancel: json['can_cancel'] == true,
        canReorder: json['can_reorder'] == true,
        canResumePayment: json['can_resume_payment'] == true,
      );
}

class MobileOrderItem {
  final int id;
  final int? productId;
  final String productName;
  final String imageUrl;
  final int quantity;
  final double unitPrice;
  final double subtotal;
  final String paymentStatus;
  final String deliveryStatus;
  final String deliveryStatusLabel;
  final String receptionStatus;
  final String returnStatus;

  const MobileOrderItem({
    required this.id,
    required this.productId,
    required this.productName,
    required this.imageUrl,
    required this.quantity,
    required this.unitPrice,
    required this.subtotal,
    required this.paymentStatus,
    required this.deliveryStatus,
    required this.deliveryStatusLabel,
    required this.receptionStatus,
    required this.returnStatus,
  });

  factory MobileOrderItem.fromJson(Map<String, dynamic> json) {
    final product = json['product'] is Map
        ? Map<String, dynamic>.from(json['product'] as Map)
        : <String, dynamic>{};
    double amount(dynamic value) {
      if (value is num) return value.toDouble();
      return double.tryParse('${value ?? ''}') ?? 0;
    }

    return MobileOrderItem(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      productId: int.tryParse('${product['id'] ?? ''}'),
      productName: cleanOvanieText((product['name'] ?? 'Produit OVANIE').toString()),
      imageUrl: (product['main_image_url'] ?? '').toString().trim(),
      quantity: int.tryParse('${json['quantity'] ?? ''}') ?? 0,
      unitPrice: amount(json['unit_price']),
      subtotal: amount(json['subtotal']),
      paymentStatus: (json['payment_status'] ?? '').toString().trim().toLowerCase(),
      deliveryStatus: (json['delivery_status'] ?? '').toString().trim().toLowerCase(),
      deliveryStatusLabel: cleanOvanieText((json['delivery_status_label'] ?? '').toString()),
      receptionStatus: (json['reception_status'] ?? '').toString().trim().toLowerCase(),
      returnStatus: (json['return_status'] ?? '').toString().trim().toLowerCase(),
    );
  }
}

class OrderTimelineEvent {
  final String label;
  final String message;
  final DateTime? date;
  final String type;
  final String state;
  final int? orderItemId;

  const OrderTimelineEvent({
    required this.label,
    required this.message,
    required this.date,
    required this.type,
    required this.state,
    required this.orderItemId,
  });

  factory OrderTimelineEvent.fromJson(Map<String, dynamic> json) {
    final rawDate = (json['date'] ?? '').toString().trim();
    return OrderTimelineEvent(
      label: cleanOvanieText((json['label'] ?? 'Mise à jour').toString()),
      message: cleanOvanieText((json['message'] ?? '').toString()),
      date: rawDate.isEmpty ? null : DateTime.tryParse(rawDate)?.toLocal(),
      type: (json['type'] ?? '').toString().trim().toLowerCase(),
      state: (json['state'] ?? '').toString().trim().toLowerCase(),
      orderItemId: int.tryParse('${json['order_item_id'] ?? ''}'),
    );
  }
}

class MobileOrderDetail {
  final MobileOrder order;
  final List<OrderTimelineEvent> timeline;

  const MobileOrderDetail({required this.order, required this.timeline});
}

class OrdersSummary {
  final int all;
  final int inProgress;
  final int delivered;
  final int cancelled;
  final int returned;

  const OrdersSummary({
    required this.all,
    required this.inProgress,
    required this.delivered,
    required this.cancelled,
    required this.returned,
  });

  const OrdersSummary.empty()
      : all = 0,
        inProgress = 0,
        delivered = 0,
        cancelled = 0,
        returned = 0;

  factory OrdersSummary.fromJson(Map<String, dynamic> json) => OrdersSummary(
        all: int.tryParse('${json['all'] ?? 0}') ?? 0,
        inProgress: int.tryParse('${json['in_progress'] ?? 0}') ?? 0,
        delivered: int.tryParse('${json['delivered'] ?? 0}') ?? 0,
        cancelled: int.tryParse('${json['cancelled'] ?? 0}') ?? 0,
        returned: int.tryParse('${json['returned'] ?? 0}') ?? 0,
      );

  int countFor(String bucket) => switch (bucket) {
        'delivered' => delivered,
        'cancelled' => cancelled,
        'returned' => returned,
        _ => inProgress,
      };
}

class OrdersPage {
  final List<MobileOrder> orders;
  final int currentPage;
  final int lastPage;
  final int total;
  final OrdersSummary summary;

  const OrdersPage({
    required this.orders,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.summary,
  });

  bool get hasMore => currentPage < lastPage;
}
