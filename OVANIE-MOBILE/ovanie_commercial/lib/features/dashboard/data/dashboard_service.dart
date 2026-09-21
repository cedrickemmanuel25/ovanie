import '../../../core/api/api_client.dart';
import '../models/dashboard_data.dart';

class DashboardService {
  DashboardService(this._api);

  final ApiClient _api;

  Future<CommercialDashboardData> fetch() async {
    final data = await _api.getJson('/mobile/v1/commercial/dashboard');
    return CommercialDashboardData.fromJson(data);
  }
}
