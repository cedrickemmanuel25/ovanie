import '../../../core/network/api_client.dart';
import '../domain/account_models.dart';

class ClientAccountRepository {
  const ClientAccountRepository();

  Map<String, dynamic> _dataMap(dynamic body) {
    if (body is! Map) throw const OvanieApiException('Réponse OVANIE invalide.');
    final data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);
    return Map<String, dynamic>.from(body);
  }

  Future<AccountCapabilities> capabilities() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/capabilities');
    ApiClient.ensureSuccess(response);
    return AccountCapabilities.fromJson(_dataMap(response.data));
  }

  Future<ClientProfile> profile() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/profile');
    ApiClient.ensureSuccess(response);
    return ClientProfile.fromJson(_dataMap(response.data));
  }

  Future<ClientProfile> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
    required String phone,
    required String whatsappPhone,
  }) async {
    final response = await ApiClient.dio.patch<dynamic>(
      '/mobile/client/profile',
      data: {
        'first_name': firstName.trim(),
        'last_name': lastName.trim(),
        'email': email.trim(),
        'phone': phone.trim(),
        'whatsapp_phone': whatsappPhone.trim(),
      },
    );
    ApiClient.ensureSuccess(response);
    return ClientProfile.fromJson(_dataMap(response.data));
  }

  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String confirmation,
  }) async {
    final response = await ApiClient.dio.put<dynamic>(
      '/mobile/client/password',
      data: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': confirmation,
      },
    );
    ApiClient.ensureSuccess(response);
  }

  Future<List<ClientDeviceSession>> sessions() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/sessions');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map || body['data'] is! List) return const [];
    return (body['data'] as List)
        .whereType<Map>()
        .map((item) => ClientDeviceSession.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);
  }

  Future<bool> revokeSession(String id) async {
    final response = await ApiClient.dio.delete<dynamic>('/mobile/client/sessions/$id');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    return body is Map && body['current_session_revoked'] == true;
  }

  Future<List<SavedPaymentMethod>> paymentMethods() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/payment-methods');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map || body['data'] is! List) return const [];
    return (body['data'] as List)
        .whereType<Map>()
        .map((item) => SavedPaymentMethod.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);
  }

  Future<void> addPaymentMethod({
    required String operator,
    required String accountName,
    required String phone,
    required bool isDefault,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/client/payment-methods',
      data: {
        'operator': operator,
        'account_name': accountName.trim(),
        'phone': phone.trim(),
        'is_default': isDefault,
      },
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> addCardPaymentMethod({
    required String accountName,
    required String brand,
    required String last4,
    required int expMonth,
    required int expYear,
    required bool isDefault,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/client/payment-methods',
      data: {
        'type': 'card',
        'account_name': accountName.trim(),
        'card_brand': brand.trim().toLowerCase(),
        'card_last4': last4.trim(),
        'card_exp_month': expMonth,
        'card_exp_year': expYear,
        'is_default': isDefault,
      },
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> setDefaultPaymentMethod(int id) async {
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/payment-methods/$id/default');
    ApiClient.ensureSuccess(response);
  }

  Future<void> deletePaymentMethod(int id) async {
    final response = await ApiClient.dio.delete<dynamic>('/mobile/client/payment-methods/$id');
    ApiClient.ensureSuccess(response);
  }

  Future<NotificationPageData> notifications({int page = 1}) async {
    final response = await ApiClient.dio.get<dynamic>(
      '/mobile/client/notifications',
      queryParameters: {'page': page, 'per_page': 30},
    );
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map) throw const OvanieApiException('Les notifications sont indisponibles.');
    final raw = body['data'] is List ? body['data'] as List : const [];
    final meta = body['meta'] is Map ? Map<String, dynamic>.from(body['meta'] as Map) : <String, dynamic>{};
    return NotificationPageData(
      items: raw
          .whereType<Map>()
          .map((item) => ClientNotificationItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
      unreadCount: int.tryParse('${meta['unread_count'] ?? 0}') ?? 0,
      currentPage: int.tryParse('${meta['current_page'] ?? 1}') ?? 1,
      lastPage: int.tryParse('${meta['last_page'] ?? 1}') ?? 1,
    );
  }

  Future<void> markNotificationRead(String id) async {
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/notifications/$id/read');
    ApiClient.ensureSuccess(response);
  }

  Future<void> markAllNotificationsRead() async {
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/notifications/read-all');
    ApiClient.ensureSuccess(response);
  }

  Future<NotificationPreferencesData> notificationPreferences() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/notification-preferences');
    ApiClient.ensureSuccess(response);
    return NotificationPreferencesData.fromJson(_dataMap(response.data));
  }

  Future<NotificationPreferencesData> updateNotificationPreferences(
    Map<String, Map<String, bool>> preferences,
  ) async {
    final response = await ApiClient.dio.put<dynamic>(
      '/mobile/client/notification-preferences',
      data: {'preferences': preferences},
    );
    ApiClient.ensureSuccess(response);
    return NotificationPreferencesData.fromJson(_dataMap(response.data));
  }

  Future<AccountDeletionStatus> accountDeletionStatus() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/account-deletion');
    ApiClient.ensureSuccess(response);
    return AccountDeletionStatus.fromJson(_dataMap(response.data));
  }

  Future<String> requestAccountDeletionCode() async {
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/account-deletion/code');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    return body is Map ? '${body['message'] ?? 'Code envoyé.'}' : 'Code envoyé.';
  }

  Future<String> deleteAccount({required String confirmation, String password = '', String deletionCode = ''}) async {
    final response = await ApiClient.dio.delete<dynamic>(
      '/mobile/client/account',
      data: {
        'delete_confirmation': confirmation.trim(),
        'password': password,
        'deletion_code': deletionCode,
      },
    );
    ApiClient.ensureSuccess(response);
    final body = response.data;
    return body is Map ? '${body['message'] ?? 'Compte supprimé.'}' : 'Compte supprimé.';
  }

  Future<LoyaltyData> loyalty() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/loyalty');
    ApiClient.ensureSuccess(response);
    return LoyaltyData.fromJson(_dataMap(response.data));
  }

  Future<List<LegalDocumentItem>> legalDocuments() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/legal');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map || body['data'] is! List) return const [];
    return (body['data'] as List)
        .whereType<Map>()
        .map((item) => LegalDocumentItem.fromJson(Map<String, dynamic>.from(item)))
        .toList(growable: false);
  }
}
