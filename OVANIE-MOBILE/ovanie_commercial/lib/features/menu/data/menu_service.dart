import '../../../core/api/api_client.dart';
import '../models/menu_data.dart';

class MenuService {
  MenuService(this._api);
  final ApiClient _api;
  ApiClient get api => _api;

  Future<MenuOverviewData> overview() async {
    return MenuOverviewData.fromJson(
      await _api.getJson('/mobile/v1/commercial/menu/overview'),
    );
  }

  Future<MenuProfileData> profile() async {
    final json = await _api.getJson('/mobile/v1/commercial/menu/profile');
    final raw = json['profile'];
    return MenuProfileData.fromJson(
      raw is Map ? Map<String, dynamic>.from(raw) : const {},
    );
  }

  Future<List<MenuNotificationData>> notifications() async {
    final json = await _api.getJson('/mobile/v1/commercial/menu/notifications');
    final raw = json['items'];
    return raw is List
        ? raw
            .whereType<Map>()
            .map((e) => MenuNotificationData.fromJson(Map<String, dynamic>.from(e)))
            .toList(growable: false)
        : const [];
  }

  Future<void> markAllNotificationsRead() async {
    await _api.postJson('/mobile/v1/commercial/menu/notifications/read-all');
  }

  Future<void> markNotificationRead(String id) async {
    if (id.isEmpty) return;
    await _api.postJson('/mobile/v1/commercial/menu/notifications/$id/read');
  }

  Future<MenuPreferencesData> preferences() async {
    final json = await _api.getJson('/mobile/v1/commercial/menu/preferences');
    final raw = json['preferences'];
    return MenuPreferencesData.fromJson(
      raw is Map ? Map<String, dynamic>.from(raw) : const {},
    );
  }

  Future<MenuPreferencesData> savePreferences(MenuPreferencesData preferences) async {
    final json = await _api.postJson(
      '/mobile/v1/commercial/menu/preferences',
      body: preferences.toJson(),
    );
    final raw = json['preferences'];
    return MenuPreferencesData.fromJson(
      raw is Map ? Map<String, dynamic>.from(raw) : preferences.toJson(),
    );
  }

  Future<VisitedShopsData> visits() async {
    return VisitedShopsData.fromJson(
      await _api.getJson('/mobile/v1/commercial/menu/visits'),
    );
  }

  Future<DraftProductsData> drafts() async {
    return DraftProductsData.fromJson(
      await _api.getJson('/mobile/v1/commercial/menu/drafts'),
    );
  }

  Future<MenuSyncData> syncStatus() async {
    return MenuSyncData.fromJson(
      await _api.getJson('/mobile/v1/commercial/menu/sync'),
    );
  }

  Future<MenuSyncData> synchronizeNow() async {
    return MenuSyncData.fromJson(
      await _api.postJson('/mobile/v1/commercial/menu/sync'),
    );
  }
}
