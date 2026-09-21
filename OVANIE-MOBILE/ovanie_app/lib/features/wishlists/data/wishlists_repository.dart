import '../../../core/network/api_client.dart';
import '../../products/domain/product_model.dart';
import '../domain/wishlist_model.dart';

class WishlistsRepository {
  const WishlistsRepository();

  Future<List<WishlistModel>> fetchAll() async {
    final response = await ApiClient.dio.get<dynamic>('/wishlists');
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is! Map || data['data'] is! List) return const <WishlistModel>[];

    return (data['data'] as List)
        .whereType<Map>()
        .map((raw) => WishlistModel.fromJson(Map<String, dynamic>.from(raw)))
        .where((wishlist) => wishlist.id > 0)
        .toList(growable: false);
  }

  Future<WishlistModel> create(String name) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/wishlists',
      data: <String, dynamic>{'name': name},
    );
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is! Map || data['data'] is! Map) {
      throw const OvanieApiException('La liste n’a pas pu être créée.');
    }
    return WishlistModel.fromJson(Map<String, dynamic>.from(data['data'] as Map));
  }

  Future<WishlistModel> update(
    int wishlistId, {
    String? name,
    bool? alertsEnabled,
  }) async {
    final payload = <String, dynamic>{};
    if (name != null) payload['name'] = name;
    if (alertsEnabled != null) payload['alerts_enabled'] = alertsEnabled;

    final response = await ApiClient.dio.patch<dynamic>(
      '/wishlists/$wishlistId',
      data: payload,
    );
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is! Map || data['data'] is! Map) {
      throw const OvanieApiException('La liste n’a pas pu être mise à jour.');
    }
    return WishlistModel.fromJson(Map<String, dynamic>.from(data['data'] as Map));
  }

  Future<void> delete(int wishlistId) async {
    final response = await ApiClient.dio.delete<dynamic>('/wishlists/$wishlistId');
    ApiClient.ensureSuccess(response);
  }

  Future<void> addProduct(int wishlistId, ProductModel product) async {
    final response = await ApiClient.dio.put<dynamic>(
      '/wishlists/$wishlistId/products/${product.id}',
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> removeProduct(int wishlistId, int productId) async {
    final response = await ApiClient.dio.delete<dynamic>(
      '/wishlists/$wishlistId/products/$productId',
    );
    ApiClient.ensureSuccess(response);
  }
}
