import '../../../core/utils/text_cleaner.dart';

class ReturnCenterData {
  final List<ClientReturnCase> requests;
  final List<EligibleReturnOrder> eligibleOrders;

  const ReturnCenterData({required this.requests, required this.eligibleOrders});

  factory ReturnCenterData.fromJson(Map<String, dynamic> json) {
    final rawRequests = json['requests'] is List ? json['requests'] as List : const [];
    final rawOrders = json['eligible_orders'] is List ? json['eligible_orders'] as List : const [];
    return ReturnCenterData(
      requests: rawRequests
          .whereType<Map>()
          .map((item) => ClientReturnCase.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
      eligibleOrders: rawOrders
          .whereType<Map>()
          .map((item) => EligibleReturnOrder.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
    );
  }
}

class ClientReturnCase {
  final int id;
  final int? orderId;
  final String orderNumber;
  final int? orderItemId;
  final int? productId;
  final String productName;
  final String imageUrl;
  final int quantity;
  final String type;
  final String typeLabel;
  final String reason;
  final String status;
  final String statusLabel;
  final String logisticsStatus;
  final String logisticsStatusLabel;
  final String vendorResponse;
  final double? refundAmount;
  final double? unitPrice;
  final int photoCount;
  final int videoCount;
  final DateTime? requestDate;
  final DateTime? acceptedAt;
  final DateTime? rejectedAt;
  final DateTime? refundedAt;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final DateTime? resolvedAt;

  // Contexte réel de la commande utilisé par l'assistant de préparation.
  final DateTime? orderCreatedAt;
  final String deliveryAddress;
  final String deliveryCity;
  final String deliveryCommune;
  final String deliveryQuartier;
  final String recipientName;
  final String recipientPhone;
  final String recipientEmail;

  // Informations de préparation enregistrées dans returns.meta.
  final String detailedDescription;
  final DateTime? discoveryDate;
  final String storageLocation;
  final String pickupAddress;
  final String pickupContactName;
  final String pickupContactPhone;
  final String pickupContactEmail;
  final DateTime? pickupDate;
  final String pickupTimeSlot;

  const ClientReturnCase({
    required this.id,
    required this.orderId,
    required this.orderNumber,
    required this.orderItemId,
    required this.productId,
    required this.productName,
    required this.imageUrl,
    required this.quantity,
    required this.type,
    required this.typeLabel,
    required this.reason,
    required this.status,
    required this.statusLabel,
    required this.logisticsStatus,
    required this.logisticsStatusLabel,
    required this.vendorResponse,
    required this.refundAmount,
    required this.unitPrice,
    required this.photoCount,
    required this.videoCount,
    required this.requestDate,
    required this.acceptedAt,
    required this.rejectedAt,
    required this.refundedAt,
    required this.createdAt,
    required this.updatedAt,
    required this.resolvedAt,
    required this.orderCreatedAt,
    required this.deliveryAddress,
    required this.deliveryCity,
    required this.deliveryCommune,
    required this.deliveryQuartier,
    required this.recipientName,
    required this.recipientPhone,
    required this.recipientEmail,
    required this.detailedDescription,
    required this.discoveryDate,
    required this.storageLocation,
    required this.pickupAddress,
    required this.pickupContactName,
    required this.pickupContactPhone,
    required this.pickupContactEmail,
    required this.pickupDate,
    required this.pickupTimeSlot,
  });

  bool get isClosed => const {'rejected', 'closed', 'resolved', 'refunded', 'cancelled'}.contains(status);
  bool get isApproved => status == 'accepted' || status == 'closed' || status == 'resolved' || status == 'refunded';
  bool get hasPickupPlan => pickupDate != null || logisticsStatus == 'return_pickup_planned';

  String get collectionAddress {
    if (pickupAddress.trim().isNotEmpty) return pickupAddress.trim();
    final parts = <String>[
      deliveryAddress,
      deliveryQuartier,
      deliveryCommune,
      deliveryCity,
    ].where((value) => value.trim().isNotEmpty).toList(growable: false);
    return parts.join(', ');
  }

  factory ClientReturnCase.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> map(dynamic value) =>
        value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
    DateTime? date(dynamic value) {
      final raw = value?.toString().trim() ?? '';
      return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
    }

    final product = map(json['product']);
    final order = map(json['order_context']);
    final preparation = map(json['preparation']);
    final refundRaw = json['refund_amount'];
    final refundAmount = refundRaw is num ? refundRaw.toDouble() : double.tryParse('${refundRaw ?? ''}');
    final unitPriceRaw = json['unit_price'];
    final unitPrice = unitPriceRaw is num ? unitPriceRaw.toDouble() : double.tryParse('${unitPriceRaw ?? ''}');

    return ClientReturnCase(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      orderId: int.tryParse('${json['order_id'] ?? ''}'),
      orderNumber: cleanOvanieText((json['order_number'] ?? '').toString()),
      orderItemId: int.tryParse('${json['order_item_id'] ?? ''}'),
      productId: int.tryParse('${product['id'] ?? ''}'),
      productName: cleanOvanieText((product['name'] ?? 'Produit OVANIE').toString()),
      imageUrl: (product['main_image_url'] ?? '').toString().trim(),
      quantity: int.tryParse('${json['quantity'] ?? 1}') ?? 1,
      type: (json['type'] ?? 'return').toString().trim().toLowerCase(),
      typeLabel: cleanOvanieText((json['type_label'] ?? 'Retour').toString()),
      reason: cleanOvanieText((json['reason'] ?? '').toString()),
      status: (json['status'] ?? '').toString().trim().toLowerCase(),
      statusLabel: cleanOvanieText((json['status_label'] ?? 'En cours de traitement').toString()),
      logisticsStatus: (json['logistics_status'] ?? '').toString().trim().toLowerCase(),
      logisticsStatusLabel: cleanOvanieText((json['logistics_status_label'] ?? '').toString()),
      vendorResponse: cleanOvanieText((json['vendor_response'] ?? '').toString()),
      refundAmount: refundAmount,
      unitPrice: unitPrice,
      photoCount: int.tryParse('${json['photo_count'] ?? 0}') ?? 0,
      videoCount: int.tryParse('${json['video_count'] ?? 0}') ?? 0,
      requestDate: date(json['request_date']),
      acceptedAt: date(json['accepted_at']),
      rejectedAt: date(json['rejected_at']),
      refundedAt: date(json['refunded_at']),
      createdAt: date(json['created_at']),
      updatedAt: date(json['updated_at']),
      resolvedAt: date(json['resolved_at']),
      orderCreatedAt: date(order['created_at']),
      deliveryAddress: cleanOvanieText((order['delivery_address'] ?? '').toString()),
      deliveryCity: cleanOvanieText((order['delivery_city'] ?? '').toString()),
      deliveryCommune: cleanOvanieText((order['delivery_commune'] ?? '').toString()),
      deliveryQuartier: cleanOvanieText((order['delivery_quartier'] ?? '').toString()),
      recipientName: cleanOvanieText((order['recipient_name'] ?? '').toString()),
      recipientPhone: cleanOvanieText((order['recipient_phone'] ?? '').toString()),
      recipientEmail: cleanOvanieText((order['recipient_email'] ?? '').toString()),
      detailedDescription: cleanOvanieText((preparation['detailed_description'] ?? '').toString()),
      discoveryDate: date(preparation['discovery_date']),
      storageLocation: cleanOvanieText((preparation['storage_location'] ?? '').toString()),
      pickupAddress: cleanOvanieText((preparation['pickup_address'] ?? '').toString()),
      pickupContactName: cleanOvanieText((preparation['pickup_contact_name'] ?? '').toString()),
      pickupContactPhone: cleanOvanieText((preparation['pickup_contact_phone'] ?? '').toString()),
      pickupContactEmail: cleanOvanieText((preparation['pickup_contact_email'] ?? '').toString()),
      pickupDate: date(preparation['pickup_date']),
      pickupTimeSlot: cleanOvanieText((preparation['pickup_time_slot'] ?? '').toString()),
    );
  }
}

class EligibleReturnOrder {
  final int id;
  final String orderNumber;
  final DateTime? createdAt;
  final String deliveryAddress;
  final String deliveryCity;
  final String deliveryCommune;
  final String deliveryQuartier;
  final String recipientName;
  final String recipientPhone;
  final String recipientEmail;
  final List<EligibleReturnItem> items;

  const EligibleReturnOrder({
    required this.id,
    required this.orderNumber,
    required this.createdAt,
    required this.deliveryAddress,
    required this.deliveryCity,
    required this.deliveryCommune,
    required this.deliveryQuartier,
    required this.recipientName,
    required this.recipientPhone,
    required this.recipientEmail,
    required this.items,
  });

  String get collectionAddress {
    final parts = <String>[
      deliveryAddress,
      deliveryQuartier,
      deliveryCommune,
      deliveryCity,
    ].where((value) => value.trim().isNotEmpty).toList(growable: false);
    return parts.join(', ');
  }

  factory EligibleReturnOrder.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'] is List ? json['items'] as List : const [];
    final rawDate = (json['created_at'] ?? '').toString().trim();
    return EligibleReturnOrder(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      orderNumber: cleanOvanieText((json['order_number'] ?? '').toString()),
      createdAt: rawDate.isEmpty ? null : DateTime.tryParse(rawDate)?.toLocal(),
      deliveryAddress: cleanOvanieText((json['delivery_address'] ?? '').toString()),
      deliveryCity: cleanOvanieText((json['delivery_city'] ?? '').toString()),
      deliveryCommune: cleanOvanieText((json['delivery_commune'] ?? '').toString()),
      deliveryQuartier: cleanOvanieText((json['delivery_quartier'] ?? '').toString()),
      recipientName: cleanOvanieText((json['recipient_name'] ?? '').toString()),
      recipientPhone: cleanOvanieText((json['recipient_phone'] ?? '').toString()),
      recipientEmail: cleanOvanieText((json['recipient_email'] ?? '').toString()),
      items: rawItems
          .whereType<Map>()
          .map((item) => EligibleReturnItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
    );
  }
}

class EligibleReturnItem {
  final int id;
  final int? productId;
  final String productName;
  final String imageUrl;
  final double? unitPrice;
  final int orderedQuantity;
  final int availableReturnQuantity;
  final int availableClaimQuantity;
  final bool canClaim;
  final bool canReturn;
  final bool canRefund;
  final DateTime? returnDeadline;
  final bool delivered;

  const EligibleReturnItem({
    required this.id,
    required this.productId,
    required this.productName,
    required this.imageUrl,
    required this.unitPrice,
    required this.orderedQuantity,
    required this.availableReturnQuantity,
    required this.availableClaimQuantity,
    required this.canClaim,
    required this.canReturn,
    required this.canRefund,
    required this.returnDeadline,
    required this.delivered,
  });

  int maxQuantityFor(String type) => switch (type) {
        'claim' => availableClaimQuantity,
        _ => availableReturnQuantity,
      };

  bool supports(String type) => switch (type) {
        'claim' => canClaim,
        'refund' => canRefund,
        _ => canReturn,
      };

  factory EligibleReturnItem.fromJson(Map<String, dynamic> json) {
    final product = json['product'] is Map
        ? Map<String, dynamic>.from(json['product'] as Map)
        : <String, dynamic>{};
    final rawDeadline = (json['return_deadline'] ?? '').toString().trim();
    final rawPrice = json['unit_price'];
    return EligibleReturnItem(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      productId: int.tryParse('${product['id'] ?? ''}'),
      productName: cleanOvanieText((product['name'] ?? 'Produit OVANIE').toString()),
      imageUrl: (product['main_image_url'] ?? '').toString().trim(),
      unitPrice: rawPrice is num ? rawPrice.toDouble() : double.tryParse('${rawPrice ?? ''}'),
      orderedQuantity: int.tryParse('${json['ordered_quantity'] ?? 0}') ?? 0,
      availableReturnQuantity: int.tryParse('${json['available_return_quantity'] ?? 0}') ?? 0,
      availableClaimQuantity: int.tryParse('${json['available_claim_quantity'] ?? 0}') ?? 0,
      canClaim: json['can_claim'] == true,
      canReturn: json['can_return'] == true,
      canRefund: json['can_refund'] == true,
      returnDeadline: rawDeadline.isEmpty ? null : DateTime.tryParse(rawDeadline)?.toLocal(),
      delivered: json['delivered'] == true,
    );
  }
}
