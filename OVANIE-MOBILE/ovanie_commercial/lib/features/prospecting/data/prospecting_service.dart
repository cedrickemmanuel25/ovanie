import '../../../core/api/api_client.dart';
import '../models/prospecting_data.dart';

class ProspectingService {
  ProspectingService(this._api);
  final ApiClient _api;

  Future<ProspectingOverviewData> overview() async => ProspectingOverviewData.fromJson(
        await _api.getJson('/mobile/v1/commercial/prospecting/mission'),
      );

  Future<List<ProspectData>> prospects({int? missionId, int? quarterId, String query = ''}) async {
    final json = await _api.getJson(
      '/mobile/v1/commercial/prospecting/prospects',
      queryParameters: {
        if (missionId != null) 'mission_id': missionId,
        if (quarterId != null) 'quarter_id': quarterId,
        if (query.trim().isNotEmpty) 'q': query.trim(),
      },
    );
    final rows = json['prospects'];
    return rows is List
        ? rows.whereType<Map>().map((e) => ProspectData.fromJson(Map<String, dynamic>.from(e))).toList()
        : [];
  }

  Future<void> startQuarter(int missionId, int missionQuarterId) async {
    await _api.postJson('/mobile/v1/commercial/prospecting/missions/$missionId/quarters/$missionQuarterId/start');
  }

  Future<void> finishQuarter(int missionId, int missionQuarterId) async {
    await _api.postJson('/mobile/v1/commercial/prospecting/missions/$missionId/quarters/$missionQuarterId/finish');
  }

  Future<ProspectData> createProspect({
    required ProspectingMissionData mission,
    required MissionQuarterData quarter,
    required String businessName,
    String category = '',
    String contactName = '',
    String phone = '',
    String whatsapp = '',
    String address = '',
    String landmark = '',
    String potential = 'medium',
    String notes = '',
  }) async {
    final json = await _api.postJson(
      '/mobile/v1/commercial/prospecting/prospects',
      body: {
        'mission_id': mission.id,
        'quarter_id': quarter.quarterId,
        'business_name': businessName,
        'category': category,
        'contact_name': contactName,
        'phone': phone,
        'whatsapp': whatsapp,
        'address': address,
        'landmark': landmark,
        'potential': potential,
        'notes': notes,
      },
    );
    return ProspectData.fromJson(Map<String, dynamic>.from(json['prospect'] as Map));
  }

  Future<ProspectData> visit(
    int id, {
    required String outcome,
    String notes = '',
    DateTime? nextFollowUp,
  }) async {
    final json = await _api.postJson(
      '/mobile/v1/commercial/prospecting/prospects/$id/visits',
      body: {
        'outcome': outcome,
        'notes': notes,
        if (nextFollowUp != null) 'next_follow_up_at': nextFollowUp.toIso8601String(),
      },
    );
    return ProspectData.fromJson(Map<String, dynamic>.from(json['prospect'] as Map));
  }
}
