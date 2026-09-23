import '../../../core/network/api_client.dart';
import '../models/driver_mission.dart';

class MissionRepository {
  MissionRepository._();

  static final MissionRepository instance = MissionRepository._();


  Future<DriverMapConfig> fetchMapConfig() async {
    final response = await ApiClient.dio.get<dynamic>('/driver/map-config');
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      return DriverMapConfig.fromJson(Map<String, dynamic>.from(data));
    }
    throw const OvanieApiException(
      'Configuration cartographique OVANIE indisponible.',
    );
  }

  Future<DriverMissionListResult> fetchMissions() async {
    final response = await ApiClient.dio.get<dynamic>('/driver/missions');
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      return DriverMissionListResult.fromJson(Map<String, dynamic>.from(data));
    }
    throw const OvanieApiException('Liste des missions indisponible.');
  }

  Future<DriverMissionDetail> fetchMission(String missionNumber) async {
    final response = await ApiClient.dio.get<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}',
    );
    ApiClient.ensureSuccess(response);
    return _missionFromResponse(response.data);
  }

  Future<DriverMissionDetail> accept(String missionNumber) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/accept',
    );
    ApiClient.ensureSuccess(response);
    return _missionFromResponse(response.data);
  }

  Future<void> reject(String missionNumber, {String? reason}) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/reject',
      data: {
        if ((reason ?? '').trim().isNotEmpty) 'reason': reason!.trim(),
      },
    );
    ApiClient.ensureSuccess(response);
  }

  Future<DriverMissionDetail> start(String missionNumber) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/start',
    );
    ApiClient.ensureSuccess(response);
    return _missionFromResponse(response.data);
  }

  Future<DriverMissionDetail> updatePickupStep(
    String missionNumber,
    String stopId, {
    required String action,
    bool? itemsChecked,
    bool? quantitiesChecked,
    bool? conditionChecked,
    bool? handoverConfirmed,
    String? pickupCode,
    String? notes,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/pickups/${Uri.encodeComponent(stopId)}/complete',
      data: {
        'action': action,
        if (itemsChecked != null) 'items_checked': itemsChecked,
        if (quantitiesChecked != null) 'quantities_checked': quantitiesChecked,
        if (conditionChecked != null) 'condition_checked': conditionChecked,
        if (handoverConfirmed != null) 'handover_confirmed': handoverConfirmed,
        if ((pickupCode ?? '').trim().isNotEmpty) 'pickup_code': pickupCode!.trim(),
        if ((notes ?? '').trim().isNotEmpty) 'notes': notes!.trim(),
      },
    );
    ApiClient.ensureSuccess(response);
    return _missionFromResponse(response.data);
  }

  Future<DriverMissionDetail> updateStage(
    String missionNumber,
    String stage,
  ) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/stage',
      data: {'stage': stage},
    );
    ApiClient.ensureSuccess(response);
    return _missionFromResponse(response.data);
  }

  Future<DriverMissionDetail> verifyOtp(
    String missionNumber,
    String otpCode, {
    required bool handoverConfirmed,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/verify-otp',
      data: {
        'delivery_otp_code': otpCode,
        'handover_confirmed': handoverConfirmed,
      },
    );
    ApiClient.ensureSuccess(response);
    return _missionFromResponse(response.data);
  }

  Future<void> recordLocation(
    String missionNumber, {
    required double latitude,
    required double longitude,
    double? accuracy,
    double? speed,
    double? heading,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/location',
      data: {
        'latitude': latitude,
        'longitude': longitude,
        if (accuracy != null) 'accuracy': accuracy,
        if (speed != null && speed >= 0) 'speed': speed,
        if (heading != null && heading >= 0) 'heading': heading,
      },
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> markGpsUnavailable(
    String missionNumber, {
    required String reason,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/gps-unavailable',
      data: {'reason': reason},
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> reportIncident(
    String missionNumber, {
    required String incidentType,
    String? description,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/missions/${Uri.encodeComponent(missionNumber)}/incident',
      data: {
        'incident_type': incidentType,
        if ((description ?? '').trim().isNotEmpty)
          'description': description!.trim(),
      },
    );
    ApiClient.ensureSuccess(response);
  }

  DriverMissionDetail _missionFromResponse(dynamic data) {
    if (data is Map) {
      final map = Map<String, dynamic>.from(data);
      final mission = map['mission'];
      if (mission is Map) {
        return DriverMissionDetail.fromJson(Map<String, dynamic>.from(mission));
      }
      if (map['mission_number'] != null) {
        return DriverMissionDetail.fromJson(map);
      }
    }
    throw const OvanieApiException('Détail de mission indisponible.');
  }
}

class DriverMapConfig {
  const DriverMapConfig({
    required this.provider,
    required this.accessToken,
    required this.styleUri,
  });

  final String provider;
  final String accessToken;
  final String styleUri;

  factory DriverMapConfig.fromJson(Map<String, dynamic> json) {
    return DriverMapConfig(
      provider: (json['provider'] ?? 'mapbox').toString(),
      accessToken: (json['access_token'] ?? '').toString(),
      styleUri: (json['style_uri'] ?? '').toString(),
    );
  }
}
