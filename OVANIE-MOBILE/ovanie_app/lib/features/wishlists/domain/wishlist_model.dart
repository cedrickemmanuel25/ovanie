import '../../products/domain/product_model.dart';

class WishlistModel {
  final int id;
  final String name;
  final bool alertsEnabled;
  final DateTime? updatedAt;
  final List<ProductModel> products;

  const WishlistModel({
    required this.id,
    required this.name,
    required this.alertsEnabled,
    required this.updatedAt,
    required this.products,
  });

  int get productsCount => products.length;

  factory WishlistModel.fromJson(Map<String, dynamic> json) {
    int toInt(dynamic value) {
      if (value is num) return value.toInt();
      return int.tryParse(value?.toString() ?? '') ?? 0;
    }

    final rawProducts = json['products'];
    final products = rawProducts is List
        ? rawProducts
            .whereType<Map>()
            .map((raw) => ProductModel.fromJson(Map<String, dynamic>.from(raw)))
            .where((product) => product.id > 0)
            .toList(growable: false)
        : const <ProductModel>[];

    DateTime? updatedAt;
    final rawUpdatedAt = json['updated_at']?.toString();
    if (rawUpdatedAt != null && rawUpdatedAt.trim().isNotEmpty) {
      updatedAt = DateTime.tryParse(rawUpdatedAt);
    }

    return WishlistModel(
      id: toInt(json['id']),
      name: json['name']?.toString().trim().isNotEmpty == true
          ? json['name'].toString().trim()
          : 'Ma liste',
      alertsEnabled: json['alerts_enabled'] == true || json['alerts_enabled'] == 1,
      updatedAt: updatedAt,
      products: products,
    );
  }
}
