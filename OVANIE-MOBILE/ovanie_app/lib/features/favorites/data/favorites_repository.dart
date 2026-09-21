import '../../../core/network/api_client.dart';
import '../../products/domain/product_model.dart';

class FavoritesRepository {
  const FavoritesRepository();

  Future<List<ProductModel>> fetchAll() async {
    final response = await ApiClient.dio.get<dynamic>('/favorites');
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is! Map || data['data'] is! List) return const [];
    return (data['data'] as List)
        .whereType<Map>()
        .map((raw) => ProductModel.fromJson(Map<String, dynamic>.from(raw)))
        .where((product) => product.id > 0)
        .toList();
  }

  Future<void> add(int productId) async {
    final response = await ApiClient.dio.put<dynamic>('/favorites/$productId');
    ApiClient.ensureSuccess(response);
  }

  Future<void> remove(int productId) async {
    final response = await ApiClient.dio.delete<dynamic>('/favorites/$productId');
    ApiClient.ensureSuccess(response);
  }

  Future<void> clear() async {
    final response = await ApiClient.dio.delete<dynamic>('/favorites');
    ApiClient.ensureSuccess(response);
  }
}
