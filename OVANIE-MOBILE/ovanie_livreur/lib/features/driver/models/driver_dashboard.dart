class DriverDashboardData {
  const DriverDashboardData({
    required this.driver,
    required this.account,
    required this.availability,
    required this.summary,
    required this.notifications,
    this.priorityMission,
    this.currentMission,
    this.nextMission,
  });

  final DriverDashboardDriver driver;
  final DriverAccountState account;
  final DriverAvailabilityState availability;
  final DriverDashboardSummary summary;
  final List<DriverDashboardNotification> notifications;
  final DriverMissionSummary? priorityMission;
  final DriverMissionSummary? currentMission;
  final DriverMissionSummary? nextMission;

  factory DriverDashboardData.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> mapOf(dynamic value) =>
        value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

    final notifications = json['notifications'];
    return DriverDashboardData(
      driver: DriverDashboardDriver.fromJson(mapOf(json['driver'])),
      account: DriverAccountState.fromJson(mapOf(json['account'])),
      availability: DriverAvailabilityState.fromJson(mapOf(json['availability'])),
      summary: DriverDashboardSummary.fromJson(mapOf(json['summary'])),
      priorityMission: json['priority_mission'] is Map
          ? DriverMissionSummary.fromJson(mapOf(json['priority_mission']))
          : null,
      currentMission: json['current_mission'] is Map
          ? DriverMissionSummary.fromJson(mapOf(json['current_mission']))
          : null,
      nextMission: json['next_mission'] is Map
          ? DriverMissionSummary.fromJson(mapOf(json['next_mission']))
          : null,
      notifications: notifications is List
          ? notifications
              .whereType<Map>()
              .map((item) => DriverDashboardNotification.fromJson(Map<String, dynamic>.from(item)))
              .toList(growable: false)
          : const [],
    );
  }
}

class DriverDashboardDriver {
  const DriverDashboardDriver({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.phone,
    required this.availability,
    required this.isOnline,
    this.avatarUrl,
    this.vehicle,
    this.vehicleCode,
    this.vehiclePlate,
    this.vehicleColor,
    this.vehicleColorHex,
    this.vehiclePhotoUrl,
    this.vehicleReferenceAssetUrl,
    this.vehiclePhotoIsReal = false,
    this.fleetVehicleCode,
    this.vehicleBrand,
    this.vehicleModel,
    this.zone,
    this.zones = const [],
    this.zoneLabel,
    this.zoneCount = 0,
    this.gpsAccuracyM,
    this.rating,
    this.lastGpsSeenAt,
  });

  final String id;
  final String firstName;
  final String lastName;
  final String phone;
  final String availability;
  final bool isOnline;
  final String? avatarUrl;
  final String? vehicle;
  final String? vehicleCode;
  final String? vehiclePlate;
  final String? vehicleColor;
  final String? vehicleColorHex;
  final String? vehiclePhotoUrl;
  final String? vehicleReferenceAssetUrl;
  final bool vehiclePhotoIsReal;
  final String? fleetVehicleCode;
  final String? vehicleBrand;
  final String? vehicleModel;

  /// Ancien champ de compatibilité (commune principale seulement).
  final String? zone;

  /// Toutes les communes réellement choisies par le livreur.
  final List<String> zones;
  final String? zoneLabel;
  final int zoneCount;
  final double? gpsAccuracyM;
  final double? rating;
  final DateTime? lastGpsSeenAt;

  factory DriverDashboardDriver.fromJson(Map<String, dynamic> json) {
    return DriverDashboardDriver(
      id: '${json['id'] ?? ''}',
      firstName: '${json['first_name'] ?? ''}',
      lastName: '${json['last_name'] ?? ''}',
      phone: '${json['phone'] ?? ''}',
      availability: '${json['availability'] ?? 'Indisponible'}',
      isOnline: json['is_online'] == true,
      avatarUrl: json['avatar_url']?.toString(),
      vehicle: json['vehicle']?.toString(),
      vehicleCode: json['vehicle_code']?.toString(),
      vehiclePlate: json['vehicle_plate']?.toString(),
      vehicleColor: json['vehicle_color']?.toString(),
      vehicleColorHex: json['vehicle_color_hex']?.toString(),
      vehiclePhotoUrl: json['vehicle_photo_url']?.toString(),
      vehicleReferenceAssetUrl: json['vehicle_reference_asset_url']?.toString(),
      vehiclePhotoIsReal: json['vehicle_photo_is_real'] == true,
      fleetVehicleCode: json['fleet_vehicle_code']?.toString(),
      vehicleBrand: json['vehicle_brand']?.toString(),
      vehicleModel: json['vehicle_model']?.toString(),
      zone: json['zone']?.toString(),
      zones: json['zones'] is List
          ? (json['zones'] as List)
              .map((value) => value.toString().trim())
              .where((value) => value.isNotEmpty)
              .toList(growable: false)
          : const [],
      zoneLabel: json['zone_label']?.toString(),
      zoneCount: int.tryParse('${json['zone_count'] ?? 0}') ?? 0,
      gpsAccuracyM: double.tryParse('${json['gps_accuracy_m'] ?? ''}'),
      rating: double.tryParse('${json['rating'] ?? ''}'),
      lastGpsSeenAt: DateTime.tryParse('${json['last_gps_seen_at'] ?? ''}'),
    );
  }

  String get fullName => '$firstName $lastName'.trim();

  String get interventionZonesLabel {
    final apiLabel = (zoneLabel ?? '').trim();
    if (apiLabel.isNotEmpty) return apiLabel;
    if (zones.isNotEmpty) {
      final visible = zones.take(2).toList(growable: false);
      final remaining = zones.length - visible.length;
      return '${visible.join(', ')}${remaining > 0 ? ' +$remaining' : ''}';
    }
    return (zone ?? '').trim();
  }
}


class DriverAccountState {
  const DriverAccountState({required this.status, required this.isActive, this.rejectionReason});
  final String status;
  final bool isActive;
  final String? rejectionReason;

  factory DriverAccountState.fromJson(Map<String, dynamic> json) => DriverAccountState(
        status: '${json['status'] ?? ''}',
        isActive: json['is_active'] == true,
        rejectionReason: json['rejection_reason']?.toString(),
      );
}

class DriverAvailabilityState {
  const DriverAvailabilityState({
    required this.status,
    required this.canChange,
    required this.canReceiveMissions,
  });

  final String status;
  final bool canChange;
  final bool canReceiveMissions;

  factory DriverAvailabilityState.fromJson(Map<String, dynamic> json) => DriverAvailabilityState(
        status: '${json['status'] ?? 'Indisponible'}',
        canChange: json['can_change'] == true,
        canReceiveMissions: json['can_receive_missions'] == true,
      );
}

class DriverDashboardSummary {
  const DriverDashboardSummary({
    required this.missionsToday,
    required this.activeMissions,
    required this.completedToday,
    required this.completedTotal,
    required this.unreadNotifications,
  });

  final int missionsToday;
  final int activeMissions;
  final int completedToday;
  final int completedTotal;
  final int unreadNotifications;

  factory DriverDashboardSummary.fromJson(Map<String, dynamic> json) => DriverDashboardSummary(
        missionsToday: int.tryParse('${json['missions_today'] ?? 0}') ?? 0,
        activeMissions: int.tryParse('${json['active_missions'] ?? 0}') ?? 0,
        completedToday: int.tryParse('${json['completed_today'] ?? 0}') ?? 0,
        completedTotal: int.tryParse('${json['completed_total'] ?? 0}') ?? 0,
        unreadNotifications: int.tryParse('${json['unread_notifications'] ?? 0}') ?? 0,
      );
}

class DriverMissionSummary {
  const DriverMissionSummary({
    required this.missionNumber,
    required this.status,
    required this.statusLabel,
    this.orderNumber,
    this.clientName,
    this.destination,
    this.commune,
    this.pickupScheduledAt,
    this.estimatedDeliveryAt,
    this.itemCount = 0,
    this.pickupCount = 0,
    this.totalWeightKg = 0,
    this.vehicleLabel,
    this.preparationPercent = 0,
  });

  final String missionNumber;
  final String status;
  final String statusLabel;
  final String? orderNumber;
  final String? clientName;
  final String? destination;
  final String? commune;
  final DateTime? pickupScheduledAt;
  final DateTime? estimatedDeliveryAt;
  final int itemCount;
  final int pickupCount;
  final double totalWeightKg;
  final String? vehicleLabel;
  final int preparationPercent;

  factory DriverMissionSummary.fromJson(Map<String, dynamic> json) => DriverMissionSummary(
        missionNumber: '${json['mission_number'] ?? ''}',
        orderNumber: json['order_number']?.toString(),
        clientName: json['client_name']?.toString(),
        destination: json['destination']?.toString(),
        commune: json['commune']?.toString(),
        status: '${json['status'] ?? ''}',
        statusLabel: '${json['status_label'] ?? ''}',
        pickupScheduledAt: DateTime.tryParse('${json['pickup_scheduled_at'] ?? ''}'),
        estimatedDeliveryAt: DateTime.tryParse('${json['estimated_delivery_at'] ?? ''}'),
        pickupCount: int.tryParse('${json['pickup_count'] ?? 0}') ?? 0,
        itemCount: int.tryParse('${json['item_count'] ?? 0}') ?? 0,
        totalWeightKg: double.tryParse('${json['total_weight_kg'] ?? 0}') ?? 0,
        vehicleLabel: json['vehicle_label']?.toString(),
        preparationPercent: int.tryParse('${json['preparation_percent'] ?? 0}') ?? 0,
      );
}

class DriverDashboardNotification {
  const DriverDashboardNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.read,
    this.createdAt,
    this.missionNumber,
  });

  final String id;
  final String title;
  final String message;
  final bool read;
  final DateTime? createdAt;
  final String? missionNumber;

  factory DriverDashboardNotification.fromJson(Map<String, dynamic> json) => DriverDashboardNotification(
        id: '${json['id'] ?? ''}',
        title: '${json['title'] ?? 'Notification OVANIE'}',
        message: '${json['message'] ?? ''}',
        read: json['read'] == true,
        createdAt: DateTime.tryParse('${json['created_at'] ?? ''}'),
        missionNumber: json['mission_number']?.toString(),
      );
}
