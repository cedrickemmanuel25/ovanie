class ProspectingMissionBriefData {
  const ProspectingMissionBriefData({
    required this.id,
    required this.commune,
    required this.startsOn,
    required this.endsOn,
    required this.status,
    required this.teamCount,
  });

  final int id;
  final String commune;
  final String startsOn;
  final String endsOn;
  final String status;
  final int teamCount;

  factory ProspectingMissionBriefData.fromJson(Map<String, dynamic> json) {
    int n(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    String t(dynamic value) => '${value ?? ''}'.trim();
    return ProspectingMissionBriefData(
      id: n(json['id']),
      commune: t(json['commune']),
      startsOn: t(json['starts_on']),
      endsOn: t(json['ends_on']),
      status: t(json['status']),
      teamCount: n(json['team_count']),
    );
  }
}

class MissionTeamMemberData {
  const MissionTeamMemberData({required this.id, required this.name});
  final int id;
  final String name;

  factory MissionTeamMemberData.fromJson(Map<String, dynamic> json) => MissionTeamMemberData(
        id: json['id'] is num ? (json['id'] as num).toInt() : int.tryParse('${json['id'] ?? ''}') ?? 0,
        name: '${json['name'] ?? ''}'.trim(),
      );
}

class MissionQuarterData {
  const MissionQuarterData({
    required this.id,
    required this.quarterId,
    required this.name,
    required this.status,
    required this.prospectsCount,
    required this.interestedCount,
    required this.shopsCount,
    this.updatedBy,
  });

  final int id;
  final int quarterId;
  final String name;
  final String status;
  final int prospectsCount;
  final int interestedCount;
  final int shopsCount;
  final String? updatedBy;

  bool get completed => status == 'completed';
  bool get inProgress => status == 'in_progress';

  factory MissionQuarterData.fromJson(Map<String, dynamic> json) {
    int n(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    return MissionQuarterData(
      id: n(json['id']),
      quarterId: n(json['quarter_id']),
      name: '${json['name'] ?? ''}'.trim(),
      status: '${json['status'] ?? 'pending'}'.trim(),
      prospectsCount: n(json['prospects_count']),
      interestedCount: n(json['interested_count']),
      shopsCount: n(json['shops_count']),
      updatedBy: json['updated_by']?.toString(),
    );
  }
}

class ProspectingMissionData {
  const ProspectingMissionData({
    required this.id,
    required this.communeId,
    required this.commune,
    required this.startsOn,
    required this.endsOn,
    required this.status,
    required this.instructions,
    required this.progressPercent,
    required this.team,
    required this.quarters,
    this.shopTarget,
  });

  final int id;
  final int communeId;
  final String commune;
  final String startsOn;
  final String endsOn;
  final String status;
  final String instructions;
  final int progressPercent;
  final int? shopTarget;
  final List<MissionTeamMemberData> team;
  final List<MissionQuarterData> quarters;

  factory ProspectingMissionData.fromJson(Map<String, dynamic> json) {
    int n(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    int? nullableInt(dynamic value) {
      if (value == null || '$value'.trim().isEmpty) return null;
      return n(value);
    }

    return ProspectingMissionData(
      id: n(json['id']),
      communeId: n(json['commune_id']),
      commune: '${json['commune'] ?? ''}'.trim(),
      startsOn: '${json['starts_on'] ?? ''}'.trim(),
      endsOn: '${json['ends_on'] ?? ''}'.trim(),
      status: '${json['status'] ?? ''}'.trim(),
      instructions: '${json['instructions'] ?? ''}'.trim(),
      progressPercent: n(json['progress_percent']),
      shopTarget: nullableInt(json['shop_target']),
      team: json['team'] is List
          ? (json['team'] as List)
              .whereType<Map>()
              .map((e) => MissionTeamMemberData.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
      quarters: json['quarters'] is List
          ? (json['quarters'] as List)
              .whereType<Map>()
              .map((e) => MissionQuarterData.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
    );
  }
}

class ProspectData {
  const ProspectData({
    required this.id,
    required this.missionId,
    required this.communeId,
    required this.quarterId,
    required this.businessName,
    required this.category,
    required this.contactName,
    required this.phone,
    required this.whatsapp,
    required this.address,
    required this.landmark,
    required this.status,
    required this.potential,
    required this.notes,
    required this.commune,
    required this.quarter,
    required this.discoveredBy,
    this.latitude,
    this.longitude,
    this.shopId,
    this.shopName,
  });

  final int id;
  final int missionId;
  final int communeId;
  final int? quarterId;
  final double? latitude;
  final double? longitude;
  final int? shopId;
  final String businessName;
  final String category;
  final String contactName;
  final String phone;
  final String whatsapp;
  final String address;
  final String landmark;
  final String status;
  final String potential;
  final String notes;
  final String commune;
  final String quarter;
  final String discoveredBy;
  final String? shopName;

  factory ProspectData.fromJson(Map<String, dynamic> json) {
    int n(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    int? nullableInt(dynamic value) {
      if (value == null || '$value'.trim().isEmpty) return null;
      final parsed = n(value);
      return parsed == 0 ? null : parsed;
    }

    double? d(dynamic value) => value is num ? value.toDouble() : double.tryParse('${value ?? ''}');
    String t(dynamic value) => '${value ?? ''}'.trim();

    return ProspectData(
      id: n(json['id']),
      missionId: n(json['mission_id']),
      communeId: n(json['commune_id']),
      quarterId: nullableInt(json['quarter_id']),
      businessName: t(json['business_name']),
      category: t(json['category']),
      contactName: t(json['contact_name']),
      phone: t(json['phone']),
      whatsapp: t(json['whatsapp']),
      address: t(json['address']),
      landmark: t(json['landmark']),
      latitude: d(json['latitude']),
      longitude: d(json['longitude']),
      status: t(json['status']),
      potential: t(json['potential']),
      notes: t(json['notes']),
      commune: t(json['commune']),
      quarter: t(json['quarter']),
      discoveredBy: t(json['discovered_by']),
      shopId: nullableInt(json['shop_id']),
      shopName: json['shop_name']?.toString(),
    );
  }

  String get location => [quarter, commune].where((e) => e.isNotEmpty).join(', ');
}

class ProspectingOverviewData {
  const ProspectingOverviewData({
    required this.mission,
    required this.upcomingMission,
    required this.overdueMission,
    required this.quartersTotal,
    required this.quartersCompleted,
    required this.prospects,
    required this.interested,
    required this.shopsOpened,
    required this.visits,
  });

  final ProspectingMissionData? mission;
  final ProspectingMissionBriefData? upcomingMission;
  final ProspectingMissionBriefData? overdueMission;
  final int quartersTotal;
  final int quartersCompleted;
  final int prospects;
  final int interested;
  final int shopsOpened;
  final int visits;

  factory ProspectingOverviewData.fromJson(Map<String, dynamic> json) {
    int n(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    final summary = json['summary'] is Map ? Map<String, dynamic>.from(json['summary'] as Map) : <String, dynamic>{};
    return ProspectingOverviewData(
      mission: json['mission'] is Map ? ProspectingMissionData.fromJson(Map<String, dynamic>.from(json['mission'] as Map)) : null,
      upcomingMission: json['upcoming_mission'] is Map
          ? ProspectingMissionBriefData.fromJson(Map<String, dynamic>.from(json['upcoming_mission'] as Map))
          : null,
      overdueMission: json['overdue_mission'] is Map
          ? ProspectingMissionBriefData.fromJson(Map<String, dynamic>.from(json['overdue_mission'] as Map))
          : null,
      quartersTotal: n(summary['quarters_total']),
      quartersCompleted: n(summary['quarters_completed']),
      prospects: n(summary['prospects']),
      interested: n(summary['interested']),
      shopsOpened: n(summary['shops_opened']),
      visits: n(summary['visits']),
    );
  }
}
