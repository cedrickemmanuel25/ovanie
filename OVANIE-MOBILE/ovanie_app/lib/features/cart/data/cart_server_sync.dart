import '../../../core/network/api_client.dart';

class ServerCartItem {
  final int id;
  final int productId;
  final String productSlug;
  final int quantity;
  final double unitPrice;

  const ServerCartItem({
    required this.id,
    required this.productId,
    required this.productSlug,
    required this.quantity,
    required this.unitPrice,
  });
}

/// Opérations atomiques sur le panier Laravel.
///
/// Le panier web et le panier mobile utilisent le même Cart / CartItem.
/// Ces méthodes permettent donc à une suppression ou un changement de
/// quantité fait sur mobile d'être immédiatement visible sur le web.
class CartServerSync {
  const CartServerSync();

  Future<List<ServerCartItem>> fetchItems() async {
    final response = await ApiClient.dio.get<dynamic>('/cart');
    ApiClient.ensureSuccess(response);

    final data = response.data;
    if (data is! Map || data['cart'] is! Map) {
      throw const OvanieApiException(
        'Le panier OVANIE retourné par le serveur est invalide.',
      );
    }

    final rawItems = (data['cart'] as Map)['items'];
    if (rawItems is! List) return const [];

    final result = <ServerCartItem>[];
    for (final raw in rawItems) {
      if (raw is! Map || raw['product'] is! Map) continue;
      final product = raw['product'] as Map;
      final id = int.tryParse('${raw['id'] ?? ''}') ?? 0;
      final productId = int.tryParse('${product['id'] ?? ''}') ?? 0;
      final productSlug = (product['slug'] ?? '').toString().trim();
      final quantity = int.tryParse('${raw['quantity'] ?? ''}') ?? 0;
      final unitPrice = raw['unit_price'] is num
          ? (raw['unit_price'] as num).toDouble()
          : double.tryParse('${raw['unit_price'] ?? ''}') ?? 0;
      if (id > 0 && productId > 0 && quantity > 0) {
        result.add(
          ServerCartItem(
            id: id,
            productId: productId,
            productSlug: productSlug,
            quantity: quantity,
            unitPrice: unitPrice,
          ),
        );
      }
    }
    return result;
  }

  Future<void> setProductQuantity(int productId, int quantity) async {
    final items = await fetchItems();
    ServerCartItem? existing;
    for (final item in items) {
      if (item.productId == productId) {
        existing = item;
        break;
      }
    }

    if (quantity <= 0) {
      if (existing == null) return;
      final response = await ApiClient.dio.delete<dynamic>('/cart/${existing.id}');
      ApiClient.ensureSuccess(response);
      return;
    }

    if (existing == null) {
      final response = await ApiClient.dio.post<dynamic>(
        '/cart',
        data: <String, dynamic>{
          'product_id': productId,
          'quantity': quantity,
        },
      );
      ApiClient.ensureSuccess(response);
      return;
    }

    if (existing.quantity == quantity) return;

    final response = await ApiClient.dio.put<dynamic>(
      '/cart/${existing.id}',
      data: <String, dynamic>{'quantity': quantity},
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> clear() async {
    final response = await ApiClient.dio.delete<dynamic>('/cart');
    ApiClient.ensureSuccess(response);
  }
}
