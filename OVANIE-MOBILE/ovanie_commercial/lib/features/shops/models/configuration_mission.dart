class ShopConfigurationMissionData {
  const ShopConfigurationMissionData({required this.missions, required this.total});
  final List<ShopConfigurationMission> missions;
  final int total;

  factory ShopConfigurationMissionData.fromJson(Map<String, dynamic> json) {
    final rows = json['missions'];
    return ShopConfigurationMissionData(
      missions: rows is List
          ? rows.whereType<Map>().map((e) => ShopConfigurationMission.fromJson(Map<String, dynamic>.from(e))).toList()
          : const [],
      total: _int((json['summary'] as Map?)?['total']),
    );
  }
}

class ShopConfigurationMission {
  const ShopConfigurationMission({
    required this.id,
    required this.status,
    required this.configureLogistics,
    required this.configurePayout,
    required this.manageProducts,
    required this.logisticsCompleted,
    required this.payoutCompleted,
    required this.productsCompleted,
    required this.canComplete,
    required this.notes,
    required this.shop,
    this.configuration = const {},
  });

  final int id;
  final String status;
  final bool configureLogistics;
  final bool configurePayout;
  final bool manageProducts;
  final bool logisticsCompleted;
  final bool payoutCompleted;
  final bool productsCompleted;
  final bool canComplete;
  final String notes;
  final MissionShop shop;
  final Map<String, dynamic> configuration;

  factory ShopConfigurationMission.fromJson(Map<String, dynamic> json) => ShopConfigurationMission(
        id: _int(json['id']),
        status: '${json['status'] ?? 'pending'}',
        configureLogistics: json['configure_logistics'] == true,
        configurePayout: json['configure_payout'] == true,
        manageProducts: json['manage_products'] == true,
        logisticsCompleted: json['logistics_completed'] == true,
        payoutCompleted: json['payout_completed'] == true,
        productsCompleted: json['products_completed'] == true,
        canComplete: json['can_complete'] == true,
        notes: '${json['notes'] ?? ''}',
        shop: MissionShop.fromJson(json['shop']),
        configuration: json['configuration'] is Map ? Map<String, dynamic>.from(json['configuration']) : const {},
      );
}

class MissionShop {
  const MissionShop({
    required this.id,
    required this.name,
    required this.ownerName,
    required this.ownerPhone,
    required this.city,
    required this.commune,
    required this.district,
    required this.productsCount,
    required this.logisticsConfigured,
    required this.payoutConfigured,
    required this.logisticsType,
    required this.logisticsLabel,
    required this.paymentMode,
    required this.payoutOperator,
  });
  final int id;
  final String name;
  final String ownerName;
  final String ownerPhone;
  final String city;
  final String commune;
  final String district;
  final int productsCount;
  final bool logisticsConfigured;
  final bool payoutConfigured;
  final String logisticsType;
  final String logisticsLabel;
  final String paymentMode;
  final String payoutOperator;

  factory MissionShop.fromJson(dynamic raw) {
    final json = raw is Map ? Map<String, dynamic>.from(raw) : const <String, dynamic>{};
    return MissionShop(
      id: _int(json['id']),
      name: '${json['name'] ?? ''}',
      ownerName: '${json['owner_name'] ?? ''}',
      ownerPhone: '${json['owner_phone'] ?? ''}',
      city: '${json['city'] ?? ''}',
      commune: '${json['commune'] ?? ''}',
      district: '${json['district'] ?? ''}',
      productsCount: _int(json['products_count']),
      logisticsConfigured: json['logistics_configured'] == true,
      payoutConfigured: json['payout_configured'] == true,
      logisticsType: '${json['logistics_type'] ?? ''}',
      logisticsLabel: '${json['logistics_label'] ?? ''}',
      paymentMode: '${json['payment_mode'] ?? ''}',
      payoutOperator: '${json['payout_operator'] ?? ''}',
    );
  }
}

int _int(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;
