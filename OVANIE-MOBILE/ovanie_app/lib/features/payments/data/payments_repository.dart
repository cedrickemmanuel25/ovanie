import '../../../core/network/api_client.dart';
import '../domain/payment_models.dart';

class PaymentsRepository {
  const PaymentsRepository();

  Future<ClientPaymentHistoryPage> history({int page = 1, int perPage = 30}) async {
    final response = await ApiClient.dio.get<dynamic>(
      '/payments',
      queryParameters: {'page': page, 'per_page': perPage},
    );
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map) throw const OvanieApiException('L’historique des paiements est indisponible.');
    final data = body['data'] is List ? body['data'] as List : const [];
    return ClientPaymentHistoryPage(
      items: data.whereType<Map>().map((item) => ClientPaymentHistoryItem.fromJson(Map<String, dynamic>.from(item))).toList(growable: false),
      currentPage: int.tryParse('${body['current_page'] ?? page}') ?? page,
      lastPage: int.tryParse('${body['last_page'] ?? page}') ?? page,
      total: int.tryParse('${body['total'] ?? data.length}') ?? data.length,
    );
  }
}
