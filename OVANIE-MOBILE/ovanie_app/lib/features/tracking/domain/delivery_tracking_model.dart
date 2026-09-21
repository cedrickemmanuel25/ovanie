import '../../../core/utils/text_cleaner.dart';

class ClientDeliveryTracking {
  final int orderId;
  final String orderNumber;
  final String deliveryStatus;
  final DateTime? lastUpdate;
  final int pollSeconds;
  final int contractVersion;
  final TrackingOrderSummary? order;
  final List<ClientShipmentTracking> shipments;

  const ClientDeliveryTracking({
    required this.orderId,
    required this.orderNumber,
    required this.deliveryStatus,
    required this.lastUpdate,
    required this.pollSeconds,
    required this.contractVersion,
    required this.order,
    required this.shipments,
  });

  int get overallProgressIndex {
    final shipmentIndexes = shipments.map((shipment) => shipment.progressIndex).toList();
    if (shipmentIndexes.isNotEmpty) {
      final min = shipmentIndexes.reduce((a, b) => a < b ? a : b);
      if (min >= 0) return min;
    }

    final orderStatus = order?.status ?? '';
    if (const {'confirmed', 'paid'}.contains(orderStatus)) return 0;
    if (orderStatus == 'processing') return 1;
    if (orderStatus == 'shipped') return 4;
    if (const {'delivered', 'completed'}.contains(orderStatus)) return 5;
    return deliveryStatus == 'preparing' ? 1 : 0;
  }

  factory ClientDeliveryTracking.fromJson(Map<String, dynamic> json) {
    final rawShipments = json['shipments'] is List ? json['shipments'] as List : const [];
    DateTime? parseDate(dynamic value) {
      final raw = value?.toString().trim() ?? '';
      return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
    }

    final orderMap = json['order'] is Map
        ? Map<String, dynamic>.from(json['order'] as Map)
        : <String, dynamic>{};
    final seconds = int.tryParse('${json['poll_seconds'] ?? ''}') ?? 12;

    return ClientDeliveryTracking(
      orderId: int.tryParse('${json['order_id'] ?? orderMap['id'] ?? ''}') ?? 0,
      orderNumber: cleanOvanieText((json['order_number'] ?? orderMap['order_number'] ?? '').toString()),
      deliveryStatus: (json['delivery_status'] ?? '').toString().trim().toLowerCase(),
      lastUpdate: parseDate(json['last_update']),
      pollSeconds: seconds.clamp(5, 60).toInt(),
      contractVersion: int.tryParse('${json['tracking_contract_version'] ?? ''}') ?? 1,
      order: orderMap.isEmpty ? null : TrackingOrderSummary.fromJson(orderMap),
      shipments: rawShipments
          .whereType<Map>()
          .map((raw) => ClientShipmentTracking.fromJson(Map<String, dynamic>.from(raw)))
          .toList(growable: false),
    );
  }
}

class TrackingOrderSummary {
  final int id;
  final String orderNumber;
  final String status;
  final String paymentStatus;
  final String paymentMethod;
  final double total;
  final String address;
  final String commune;
  final String quartier;
  final String recipientName;
  final String phone;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  const TrackingOrderSummary({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.paymentStatus,
    required this.paymentMethod,
    required this.total,
    required this.address,
    required this.commune,
    required this.quartier,
    required this.recipientName,
    required this.phone,
    required this.createdAt,
    required this.updatedAt,
  });

  factory TrackingOrderSummary.fromJson(Map<String, dynamic> json) {
    double amount(dynamic value) {
      if (value is num) return value.toDouble();
      return double.tryParse('${value ?? ''}') ?? 0;
    }

    DateTime? asDate(dynamic value) {
      final raw = value?.toString().trim() ?? '';
      return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
    }

    return TrackingOrderSummary(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      orderNumber: cleanOvanieText((json['order_number'] ?? '').toString()),
      status: (json['status'] ?? '').toString().trim().toLowerCase(),
      paymentStatus: (json['payment_status'] ?? '').toString().trim().toLowerCase(),
      paymentMethod: (json['payment_method'] ?? '').toString().trim().toLowerCase(),
      total: amount(json['total']),
      address: cleanOvanieText((json['address'] ?? '').toString()),
      commune: cleanOvanieText((json['commune'] ?? '').toString()),
      quartier: cleanOvanieText((json['quartier'] ?? '').toString()),
      recipientName: cleanOvanieText((json['recipient_name'] ?? json['full_name'] ?? json['customer_name'] ?? '').toString()),
      phone: cleanOvanieText((json['phone'] ?? json['recipient_phone'] ?? json['customer_phone'] ?? '').toString()),
      createdAt: asDate(json['created_at']),
      updatedAt: asDate(json['updated_at']),
    );
  }
}

class ClientShipmentTracking {
  final String trackingKey;
  final int deliveryNumber;
  final String deliveryLabel;
  final String label;
  final int itemCount;
  final int referenceCount;
  final List<TrackingDeliveryItem> items;
  final String deliveryStatus;
  final String trackingPhase;
  final String trackingMode;
  final String provider;
  final DateTime? eta;
  final String etaSource;
  final DateTime? lastUpdate;
  final bool liveGpsAvailable;
  final String signalStatus;
  final double? distanceKm;
  final int? durationMinutes;
  final String routeProvider;
  final List<TrackingCoordinate> routePoints;
  final String driverName;
  final String driverPhone;
  final double? driverLatitude;
  final double? driverLongitude;
  final double? driverAccuracy;
  final double? driverHeading;
  final DateTime? driverLocationAt;
  final bool vehicleAssigned;
  final String vehicleLabel;
  final String vehiclePlate;
  final double? destinationLatitude;
  final double? destinationLongitude;
  final String destinationLabel;

  const ClientShipmentTracking({
    required this.trackingKey,
    required this.deliveryNumber,
    required this.deliveryLabel,
    required this.label,
    required this.itemCount,
    required this.referenceCount,
    required this.items,
    required this.deliveryStatus,
    required this.trackingPhase,
    required this.trackingMode,
    required this.provider,
    required this.eta,
    required this.etaSource,
    required this.lastUpdate,
    required this.liveGpsAvailable,
    required this.signalStatus,
    required this.distanceKm,
    required this.durationMinutes,
    required this.routeProvider,
    required this.routePoints,
    required this.driverName,
    required this.driverPhone,
    required this.driverLatitude,
    required this.driverLongitude,
    required this.driverAccuracy,
    required this.driverHeading,
    required this.driverLocationAt,
    required this.vehicleAssigned,
    required this.vehicleLabel,
    required this.vehiclePlate,
    required this.destinationLatitude,
    required this.destinationLongitude,
    required this.destinationLabel,
  });

  bool get isFinished => const {
        'delivered',
        'completed',
        'cancelled',
        'returned',
        'not_required',
      }.contains(deliveryStatus.trim().toLowerCase());

  bool get isInTransit => const {'in_transit', 'in_delivery', 'late', 'problem'}.contains(deliveryStatus);

  bool get hasDriverPosition => driverLatitude != null && driverLongitude != null;

  bool get hasActualAssignment => driverName.isNotEmpty || vehicleAssigned;

  bool get hasLiveMap => liveGpsAvailable && hasDriverPosition && destinationLatitude != null && destinationLongitude != null;

  int get progressIndex => switch (deliveryStatus) {
        'delivered' || 'completed' => 5,
        'in_transit' || 'in_delivery' || 'late' || 'problem' => 4,
        'picked_up' => 3,
        'assigned' => 2,
        'preparing' || 'ready_for_pickup' => 1,
        _ => trackingPhase == 'to_customer' ? 4 : 0,
      };

  factory ClientShipmentTracking.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> map(dynamic value) =>
        value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
    double? asDouble(dynamic value) => value is num ? value.toDouble() : double.tryParse('${value ?? ''}');
    int asInt(dynamic value, {int fallback = 0}) =>
        value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? fallback;
    DateTime? asDate(dynamic value) {
      final raw = value?.toString().trim() ?? '';
      return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
    }

    final delivery = map(json['delivery']);
    final driver = map(json['driver']);
    final driverLocation = map(driver['location']);
    final vehicle = map(json['vehicle']);
    final destination = map(json['destination']);
    final route = map(json['route']);
    final rawItems = delivery['items'] is List ? delivery['items'] as List : const [];

    return ClientShipmentTracking(
      trackingKey: (json['tracking_key'] ?? '').toString().trim(),
      deliveryNumber: asInt(json['delivery_number']),
      deliveryLabel: cleanOvanieText((json['delivery_label'] ?? '').toString()),
      label: cleanOvanieText((delivery['label'] ?? json['delivery_label'] ?? 'Articles de la commande').toString()),
      itemCount: asInt(delivery['item_count']),
      referenceCount: asInt(delivery['reference_count']),
      items: rawItems
          .whereType<Map>()
          .map((raw) => TrackingDeliveryItem.fromJson(Map<String, dynamic>.from(raw)))
          .toList(growable: false),
      deliveryStatus: (json['delivery_status'] ?? '').toString().trim().toLowerCase(),
      trackingPhase: (json['tracking_phase'] ?? '').toString().trim().toLowerCase(),
      trackingMode: (json['tracking_mode'] ?? '').toString().trim().toLowerCase(),
      provider: cleanOvanieText((json['provider'] ?? 'Livraison OVANIE').toString()),
      eta: asDate(json['eta']),
      etaSource: (json['eta_source'] ?? '').toString().trim().toLowerCase(),
      lastUpdate: asDate(json['last_update']),
      liveGpsAvailable: json['map_visible'] == true && json['gps_available'] == true,
      signalStatus: (json['signal_status'] ?? '').toString().trim().toLowerCase(),
      distanceKm: asDouble(route['distance_km']),
      durationMinutes: asInt(route['duration_minutes']) == 0 ? null : asInt(route['duration_minutes']),
      routeProvider: cleanOvanieText((route['provider'] ?? '').toString()),
      routePoints: _parseGeometry(route['geometry']),
      driverName: cleanOvanieText((driver['name'] ?? '').toString()),
      driverPhone: cleanOvanieText((driver['phone'] ?? '').toString()),
      driverLatitude: asDouble(driverLocation['latitude']),
      driverLongitude: asDouble(driverLocation['longitude']),
      driverAccuracy: asDouble(driverLocation['accuracy']),
      driverHeading: asDouble(driverLocation['heading']),
      driverLocationAt: asDate(driverLocation['recorded_at']),
      vehicleAssigned: vehicle['assigned'] == true,
      vehicleLabel: cleanOvanieText((vehicle['vehicle_label'] ?? driver['vehicle'] ?? '').toString()),
      vehiclePlate: cleanOvanieText((vehicle['vehicle_plate'] ?? '').toString()),
      destinationLatitude: asDouble(destination['latitude']),
      destinationLongitude: asDouble(destination['longitude']),
      destinationLabel: cleanOvanieText((destination['label'] ?? '').toString()),
    );
  }

  static List<TrackingCoordinate> _parseGeometry(dynamic geometry) {
    if (geometry is! Map) return const [];
    final map = Map<String, dynamic>.from(geometry);
    if ((map['type'] ?? '').toString().toLowerCase() != 'linestring' || map['coordinates'] is! List) {
      return const [];
    }

    final points = <TrackingCoordinate>[];
    for (final raw in map['coordinates'] as List) {
      if (raw is! List || raw.length < 2) continue;
      final lng = raw[0] is num ? (raw[0] as num).toDouble() : double.tryParse('${raw[0]}');
      final lat = raw[1] is num ? (raw[1] as num).toDouble() : double.tryParse('${raw[1]}');
      if (lat == null || lng == null || lat.abs() > 90 || lng.abs() > 180) continue;
      points.add(TrackingCoordinate(latitude: lat, longitude: lng));
    }
    return points.length >= 2 ? List.unmodifiable(points) : const [];
  }
}

class TrackingDeliveryItem {
  final int orderItemId;
  final int? productId;
  final String name;
  final int quantity;
  final String imageUrl;

  const TrackingDeliveryItem({
    required this.orderItemId,
    required this.productId,
    required this.name,
    required this.quantity,
    required this.imageUrl,
  });

  factory TrackingDeliveryItem.fromJson(Map<String, dynamic> json) => TrackingDeliveryItem(
        orderItemId: int.tryParse('${json['order_item_id'] ?? ''}') ?? 0,
        productId: int.tryParse('${json['product_id'] ?? ''}'),
        name: cleanOvanieText((json['name'] ?? 'Produit OVANIE').toString()),
        quantity: int.tryParse('${json['quantity'] ?? 1}') ?? 1,
        imageUrl: (json['image_url'] ?? '').toString().trim(),
      );
}

class TrackingCoordinate {
  final double latitude;
  final double longitude;

  const TrackingCoordinate({required this.latitude, required this.longitude});
}
