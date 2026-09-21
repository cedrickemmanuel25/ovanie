class DriverMissionListResult {
  const DriverMissionListResult({
    required this.missions,
    required this.toAcceptCount,
    required this.acceptedCount,
    required this.inProgressCount,
    required this.deliveredCount,
    required this.unreadNotifications,
  });

  final List<DriverMissionSummaryModel> missions;
  final int toAcceptCount;
  final int acceptedCount;
  final int inProgressCount;
  final int deliveredCount;
  final int unreadNotifications;

  factory DriverMissionListResult.fromJson(Map<String, dynamic> json) {
    final missionsValue = json['missions'];
    final counts = _mapOf(json['counts']);
    return DriverMissionListResult(
      missions: missionsValue is List
          ? missionsValue
              .whereType<Map>()
              .map((item) => DriverMissionSummaryModel.fromJson(
                    Map<String, dynamic>.from(item),
                  ))
              .toList(growable: false)
          : const [],
      toAcceptCount: _asInt(counts['to_accept']),
      acceptedCount: _asInt(counts['accepted']),
      inProgressCount: _asInt(counts['in_progress']),
      deliveredCount: _asInt(counts['delivered']),
      unreadNotifications: _asInt(json['unread_notifications']),
    );
  }
}

class DriverMissionSummaryModel {
  const DriverMissionSummaryModel({
    required this.missionNumber,
    required this.status,
    required this.statusLabel,
    this.orderNumber,
    this.clientName,
    this.destination,
    this.destinationLabel,
    this.commune,
    this.pickupScheduledAt,
    this.estimatedDeliveryAt,
    this.acceptedAt,
    this.rejectedAt,
    this.rejectionReason,
    this.deliveredAt,
    this.pickupCount = 0,
    this.itemCount = 0,
    this.lineCount = 0,
    this.totalWeightKg = 0,
    this.totalVolumeM3 = 0,
    this.vehicleCode,
    this.vehicleLabel,
    this.preparationPercent = 0,
    this.readyCount = 0,
    this.netAmount = 0,
  });

  final String missionNumber;
  final String status;
  final String statusLabel;
  final String? orderNumber;
  final String? clientName;
  final String? destination;
  final String? destinationLabel;
  final String? commune;
  final DateTime? pickupScheduledAt;
  final DateTime? estimatedDeliveryAt;
  final DateTime? acceptedAt;
  final DateTime? rejectedAt;
  final String? rejectionReason;
  final DateTime? deliveredAt;
  final int pickupCount;
  final int itemCount;
  final int lineCount;
  final double totalWeightKg;
  final double totalVolumeM3;
  final String? vehicleCode;
  final String? vehicleLabel;
  final int preparationPercent;
  final int readyCount;
  final double netAmount;

  factory DriverMissionSummaryModel.fromJson(Map<String, dynamic> json) {
    return DriverMissionSummaryModel(
      missionNumber: '${json['mission_number'] ?? ''}',
      status: '${json['status'] ?? ''}',
      statusLabel: '${json['status_label'] ?? ''}',
      orderNumber: json['order_number']?.toString(),
      clientName: json['client_name']?.toString(),
      destination: json['destination']?.toString(),
      destinationLabel: json['destination_label']?.toString(),
      commune: json['commune']?.toString(),
      pickupScheduledAt: _asDate(json['pickup_scheduled_at']),
      estimatedDeliveryAt: _asDate(json['estimated_delivery_at']),
      acceptedAt: _asDate(json['accepted_at']),
      rejectedAt: _asDate(json['rejected_at']),
      rejectionReason: json['rejection_reason']?.toString(),
      deliveredAt: _asDate(json['delivered_at']),
      pickupCount: _asInt(json['pickup_count']),
      itemCount: _asInt(json['item_count']),
      lineCount: _asInt(json['line_count']),
      totalWeightKg: _asDouble(json['total_weight_kg']),
      totalVolumeM3: _asDouble(json['total_volume_m3']),
      vehicleCode: json['vehicle_code']?.toString(),
      vehicleLabel: json['vehicle_label']?.toString(),
      preparationPercent: _asInt(json['preparation_percent']),
      readyCount: _asInt(json['ready_count']),
      netAmount: _asDouble(json['net_amount']),
    );
  }

  bool get isToAccept => status == 'assigned' || status == 'planned' || status == 'offered';
  bool get isOffered => status == 'offered';
  bool get isOfferExpired => status == 'offer_expired';
  bool get isAccepted => status == 'accepted';
  bool get isCollecting => status == 'collecting';
  bool get isLoaded => status == 'picked_up';
  bool get isInTransit => status == 'in_transit';
  bool get isArrived => status == 'arrived';
  bool get isDelivered => status == 'delivered';
  bool get isRejected => status == 'rejected';
  bool get hasIncident => status == 'incident';
  bool get isInProgress =>
      isCollecting || isLoaded || isInTransit || isArrived || hasIncident;
  bool get isHistory => isDelivered || isRejected || hasIncident || isOfferExpired;

  String get displayDestination {
    final preferred = (destinationLabel ?? '').trim();
    if (preferred.isNotEmpty) return preferred;
    final fallback = (destination ?? '').trim();
    if (fallback.isNotEmpty) return fallback.replaceAll(' - ', ' • ');
    return (commune ?? 'Destination à confirmer').trim();
  }
}

class DriverMissionDetail extends DriverMissionSummaryModel {
  const DriverMissionDetail({
    required super.missionNumber,
    required super.status,
    required super.statusLabel,
    super.orderNumber,
    super.clientName,
    super.destination,
    super.destinationLabel,
    super.commune,
    super.pickupScheduledAt,
    super.estimatedDeliveryAt,
    super.acceptedAt,
    super.rejectedAt,
    super.rejectionReason,
    super.deliveredAt,
    super.pickupCount,
    super.itemCount,
    super.lineCount,
    super.totalWeightKg,
    super.totalVolumeM3,
    super.vehicleCode,
    super.vehicleLabel,
    super.preparationPercent,
    super.readyCount,
    super.netAmount,
    this.destinationAddress,
    this.pickupStops = const [],
    this.routePlan,
    this.mapPoints = const [],
    this.pickupCompletedCount = 0,
    this.currentPickupStopId,
    this.gpsStatus,
    this.gpsDisabledReason,
    this.trackingPhase,
    this.routeTarget,
    this.incidentType,
    this.incidentDescription,
    this.incidentOccurredAt,
  });

  final String? destinationAddress;
  final List<DriverMissionPickupStop> pickupStops;
  final DriverMissionRoutePlan? routePlan;
  final List<DriverMissionGeoPoint> mapPoints;
  final int pickupCompletedCount;
  final String? currentPickupStopId;
  final String? gpsStatus;
  final String? gpsDisabledReason;
  final String? trackingPhase;
  final String? routeTarget;
  final String? incidentType;
  final String? incidentDescription;
  final DateTime? incidentOccurredAt;

  factory DriverMissionDetail.fromJson(Map<String, dynamic> json) {
    final summary = DriverMissionSummaryModel.fromJson(json);
    final stops = json['pickup_stops'];
    final points = json['map_points'];
    return DriverMissionDetail(
      missionNumber: summary.missionNumber,
      status: summary.status,
      statusLabel: summary.statusLabel,
      orderNumber: summary.orderNumber,
      clientName: summary.clientName,
      destination: summary.destination,
      destinationLabel: summary.destinationLabel,
      commune: summary.commune,
      pickupScheduledAt: summary.pickupScheduledAt,
      estimatedDeliveryAt: summary.estimatedDeliveryAt,
      acceptedAt: summary.acceptedAt,
      rejectedAt: summary.rejectedAt,
      rejectionReason: summary.rejectionReason,
      deliveredAt: summary.deliveredAt,
      pickupCount: summary.pickupCount,
      itemCount: summary.itemCount,
      lineCount: summary.lineCount,
      totalWeightKg: summary.totalWeightKg,
      totalVolumeM3: summary.totalVolumeM3,
      vehicleCode: summary.vehicleCode,
      vehicleLabel: summary.vehicleLabel,
      preparationPercent: summary.preparationPercent,
      readyCount: summary.readyCount,
      netAmount: summary.netAmount,
      destinationAddress: json['destination_address']?.toString(),
      incidentType: json['incident_type']?.toString(),
      incidentDescription: json['incident_description']?.toString(),
      incidentOccurredAt: _asDate(json['incident_occurred_at']),
      pickupStops: stops is List
          ? stops
              .whereType<Map>()
              .map((item) => DriverMissionPickupStop.fromJson(
                    Map<String, dynamic>.from(item),
                  ))
              .toList(growable: false)
          : const [],
      routePlan: json['route_plan'] is Map
          ? DriverMissionRoutePlan.fromJson(_mapOf(json['route_plan']))
          : null,
      mapPoints: points is List
          ? points
              .whereType<Map>()
              .map((item) => DriverMissionGeoPoint.fromJson(
                    Map<String, dynamic>.from(item),
                  ))
              .toList(growable: false)
          : const [],
      pickupCompletedCount: _asInt(json['pickup_completed_count']),
      currentPickupStopId: json['current_pickup_stop_id']?.toString(),
      gpsStatus: json['gps_status']?.toString(),
      gpsDisabledReason: json['gps_disabled_reason']?.toString(),
      trackingPhase: json['tracking_phase']?.toString(),
      routeTarget: json['route_target']?.toString(),
    );
  }

  DriverMissionPickupStop? get nextPickupStop {
    final current = currentPickupStop;
    if (current != null && !current.completed) return current;
    for (final stop in pickupStops) {
      if (!stop.completed) return stop;
    }
    return null;
  }

  DriverMissionPickupStop? get currentPickupStop {
    final id = currentPickupStopId;
    if (id == null) return null;
    for (final stop in pickupStops) {
      if (stop.id == id) return stop;
    }
    return null;
  }

  bool get allPickupsCompleted =>
      pickupStops.isNotEmpty && pickupCompletedCount >= pickupStops.length;
}

class DriverMissionPickupStop {
  const DriverMissionPickupStop({
    required this.id,
    required this.index,
    required this.label,
    required this.address,
    required this.locationLabel,
    required this.ready,
    required this.completed,
    required this.current,
    required this.itemCount,
    required this.weightKg,
    this.completedAt,
    this.latitude,
    this.longitude,
    this.items = const [],
  });

  final String id;
  final int index;
  final String label;
  final String address;
  final String locationLabel;
  final bool ready;
  final bool completed;
  final bool current;
  final int itemCount;
  final double weightKg;
  final DateTime? completedAt;
  final double? latitude;
  final double? longitude;
  final List<DriverMissionItem> items;

  factory DriverMissionPickupStop.fromJson(Map<String, dynamic> json) {
    final items = json['items'];
    return DriverMissionPickupStop(
      id: '${json['id'] ?? ''}',
      index: _asInt(json['index']),
      label: '${json['label'] ?? 'Point de collecte'}',
      address: '${json['address'] ?? 'Adresse à confirmer'}',
      locationLabel: '${json['location_label'] ?? json['address'] ?? 'Adresse à confirmer'}',
      ready: json['ready'] == true,
      completed: json['completed'] == true,
      current: json['current'] == true,
      itemCount: _asInt(json['item_count']),
      weightKg: _asDouble(json['weight_kg']),
      completedAt: _asDate(json['completed_at']),
      latitude: _asNullableDouble(json['latitude']),
      longitude: _asNullableDouble(json['longitude']),
      items: items is List
          ? items
              .whereType<Map>()
              .map((item) => DriverMissionItem.fromJson(
                    Map<String, dynamic>.from(item),
                  ))
              .toList(growable: false)
          : const [],
    );
  }

  String get itemsInline => items
      .map((item) => '${item.name} x${item.quantity}')
      .where((value) => value.trim().isNotEmpty)
      .join(', ');
}

class DriverMissionItem {
  const DriverMissionItem({
    required this.id,
    required this.name,
    required this.quantity,
  });

  final String id;
  final String name;
  final int quantity;

  factory DriverMissionItem.fromJson(Map<String, dynamic> json) {
    return DriverMissionItem(
      id: '${json['id'] ?? ''}',
      name: '${json['name'] ?? 'Article'}',
      quantity: _positiveInt(json['quantity']),
    );
  }
}

class DriverMissionRoutePlan {
  const DriverMissionRoutePlan({
    required this.success,
    this.provider,
    this.message,
    this.distanceKm,
    this.durationMinutes,
    this.trafficDelayMinutes,
    this.arrivalAt,
    this.geometry = const [],
    this.points = const [],
  });

  final bool success;
  final String? provider;
  final String? message;
  final double? distanceKm;
  final int? durationMinutes;
  final int? trafficDelayMinutes;
  final DateTime? arrivalAt;
  final List<DriverMissionCoordinate> geometry;
  final List<DriverMissionGeoPoint> points;

  factory DriverMissionRoutePlan.fromJson(Map<String, dynamic> json) {
    final geometryValue = _mapOf(json['geometry']);
    final coordinatesValue = geometryValue['coordinates'];
    final pointsValue = json['points'];
    final geometry = <DriverMissionCoordinate>[];
    if (coordinatesValue is List) {
      for (final pair in coordinatesValue) {
        if (pair is! List || pair.length < 2) continue;
        final longitude = _asNullableDouble(pair[0]);
        final latitude = _asNullableDouble(pair[1]);
        if (latitude == null || longitude == null) continue;
        geometry.add(DriverMissionCoordinate(latitude: latitude, longitude: longitude));
      }
    }

    return DriverMissionRoutePlan(
      success: json['success'] == true,
      provider: json['provider']?.toString(),
      message: json['message']?.toString(),
      distanceKm: _asNullableDouble(json['distance_km']),
      durationMinutes: json['duration_minutes'] == null
          ? null
          : _asInt(json['duration_minutes']),
      trafficDelayMinutes: json['traffic_delay_minutes'] == null
          ? null
          : _asInt(json['traffic_delay_minutes']),
      arrivalAt: _asDate(json['arrival_at']),
      geometry: geometry,
      points: pointsValue is List
          ? pointsValue
              .whereType<Map>()
              .map((item) => DriverMissionGeoPoint.fromJson(
                    Map<String, dynamic>.from(item),
                  ))
              .toList(growable: false)
          : const [],
    );
  }
}

class DriverMissionCoordinate {
  const DriverMissionCoordinate({required this.latitude, required this.longitude});

  final double latitude;
  final double longitude;
}

class DriverMissionGeoPoint {
  const DriverMissionGeoPoint({
    required this.id,
    required this.type,
    required this.name,
    required this.address,
    required this.latitude,
    required this.longitude,
    this.completed = false,
  });

  final String id;
  final String type;
  final String name;
  final String address;
  final double latitude;
  final double longitude;
  final bool completed;

  factory DriverMissionGeoPoint.fromJson(Map<String, dynamic> json) {
    return DriverMissionGeoPoint(
      id: '${json['id'] ?? ''}',
      type: '${json['type'] ?? 'point'}',
      name: '${json['name'] ?? 'Point'}',
      address: '${json['address'] ?? ''}',
      latitude: _asDouble(json['latitude']),
      longitude: _asDouble(json['longitude']),
      completed: json['completed'] == true,
    );
  }
}

Map<String, dynamic> _mapOf(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

int _asInt(dynamic value) => int.tryParse('${value ?? 0}') ?? 0;

int _positiveInt(dynamic value) {
  final parsed = _asInt(value);
  if (parsed < 1) return 1;
  if (parsed > 1000000) return 1000000;
  return parsed;
}

double _asDouble(dynamic value) => double.tryParse('${value ?? 0}') ?? 0;

double? _asNullableDouble(dynamic value) {
  if (value == null) return null;
  return double.tryParse('$value');
}

DateTime? _asDate(dynamic value) {
  if (value == null) return null;
  final text = value.toString().trim();
  if (text.isEmpty) return null;
  return DateTime.tryParse(text)?.toLocal();
}
