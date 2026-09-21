import '../../../core/network/api_client.dart';
import '../../products/domain/product_model.dart';

class RecentlyViewedRepository {
  const RecentlyViewedRepository();

  Future<List<ProductModel>> list() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/recently-viewed');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map || body['data'] is! List) return const [];

    return (body['data'] as List)
        .whereType<Map>()
        .map((entry) => Map<String, dynamic>.from(entry))
        .where((entry) => entry['product'] is Map)
        .map((entry) => ProductModel.fromJson(
              Map<String, dynamic>.from(entry['product'] as Map),
            ))
        .toList(growable: false);
  }

  Future<void> record(int productId) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/client/recently-viewed/$productId',
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> clear() async {
    final response = await ApiClient.dio.delete<dynamic>('/mobile/client/recently-viewed');
    ApiClient.ensureSuccess(response);
  }
}
