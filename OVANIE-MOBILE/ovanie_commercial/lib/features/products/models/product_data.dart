class CommercialShopOption {
  const CommercialShopOption({
    required this.id,
    required this.name,
    required this.category,
    required this.location,
    required this.productsCount,
    required this.status,
    required this.logistics,
    this.imageUrl,
  });

  final int id;
  final String name;
  final String category;
  final String location;
  final int productsCount;
  final String status;
  final String logistics;
  final String? imageUrl;

  factory CommercialShopOption.fromJson(Map<String, dynamic> j) => CommercialShopOption(
        id: (j['id'] as num?)?.toInt() ?? 0,
        name: '${j['name'] ?? ''}',
        category: '${j['category'] ?? ''}',
        location: '${j['location'] ?? ''}',
        productsCount: (j['products_count'] as num?)?.toInt() ?? 0,
        status: '${j['status'] ?? 'pending'}',
        logistics: '${j['logistics'] ?? 'OVANIE Logistics'}',
        imageUrl: j['image_url']?.toString(),
      );
}

class CaptureSessionData {
  const CaptureSessionData({
    required this.id,
    required this.shopId,
    required this.shopName,
    required this.name,
    required this.status,
    required this.captured,
    required this.completed,
    required this.drafts,
    required this.syncStatus,
    required this.date,
    required this.categoryId,
    required this.category,
    required this.subcategoryId,
    required this.subcategory,
    required this.price,
    required this.promoPrice,
    required this.stock,
    required this.weightKg,
    required this.unit,
  });

  final int id, shopId, captured, completed, drafts;
  final int? categoryId, subcategoryId;
  final String shopName, name, status, syncStatus, date, category, subcategory, unit;
  final double? price, promoPrice, stock, weightKg;

  double get progress => captured == 0 ? 0 : completed / captured;
  String get productName => name;
  double get productPrice => price ?? 0;
  double get productWeightKg => weightKg ?? 0;
  String get productUnit => unit;

  factory CaptureSessionData.fromJson(Map<String, dynamic> j) => CaptureSessionData(
        id: (j['id'] as num?)?.toInt() ?? 0,
        shopId: (j['shop_id'] as num?)?.toInt() ?? 0,
        shopName: '${j['shop_name'] ?? ''}',
        name: '${j['name'] ?? ''}',
        status: '${j['status'] ?? 'draft'}',
        captured: (j['captured'] as num?)?.toInt() ?? 0,
        completed: (j['completed'] as num?)?.toInt() ?? 0,
        drafts: (j['drafts'] as num?)?.toInt() ?? 0,
        syncStatus: '${j['sync_status'] ?? 'synced'}',
        date: '${j['date'] ?? ''}',
        categoryId: (j['category_id'] as num?)?.toInt(),
        category: '${j['category'] ?? ''}',
        subcategoryId: (j['subcategory_id'] as num?)?.toInt(),
        subcategory: '${j['subcategory'] ?? ''}',
        price: (j['price'] as num?)?.toDouble(),
        promoPrice: (j['promo_price'] as num?)?.toDouble(),
        stock: (j['stock'] as num?)?.toDouble(),
        weightKg: (j['weight_kg'] as num?)?.toDouble(),
        unit: '${j['unit'] ?? 'unité'}',
      );
}

class CapturedProductData {
  const CapturedProductData({
    required this.id,
    required this.name,
    required this.reference,
    required this.status,
    required this.imageUrl,
    required this.category,
    required this.subcategory,
    required this.capturedAt,
    required this.attributes,
    required this.price,
    required this.promoPrice,
    required this.stock,
    required this.unit,
    required this.weightKg,
    required this.adminCompletionRequired,
  });

  final int id;
  final String name, reference, status, imageUrl, category, subcategory, capturedAt, unit;
  final Map<String, dynamic> attributes;
  final double? price, promoPrice, stock, weightKg;
  final bool adminCompletionRequired;

  static bool _boolValue(dynamic value, {bool fallback = false}) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    final normalized = '${value ?? ''}'.trim().toLowerCase();
    if (['1', 'true', 'yes', 'oui'].contains(normalized)) return true;
    if (['0', 'false', 'no', 'non'].contains(normalized)) return false;
    return fallback;
  }

  factory CapturedProductData.fromJson(Map<String, dynamic> j) {
    final attrs = Map<String, dynamic>.from(j['attributes'] is Map ? j['attributes'] as Map : {});
    return CapturedProductData(
      id: (j['id'] as num?)?.toInt() ?? 0,
      name: '${j['name'] ?? 'Produit à compléter'}',
      reference: '${j['reference'] ?? ''}',
      status: '${j['status'] ?? 'draft'}',
      imageUrl: '${j['image_url'] ?? ''}',
      category: '${j['category'] ?? ''}',
      subcategory: '${j['subcategory'] ?? ''}',
      capturedAt: '${j['captured_at'] ?? ''}',
      attributes: attrs,
      price: (j['price'] as num?)?.toDouble(),
      promoPrice: (j['promo_price'] as num?)?.toDouble(),
      stock: (j['stock'] as num?)?.toDouble(),
      unit: '${j['unit'] ?? 'unité'}',
      weightKg: (j['weight_kg'] as num?)?.toDouble(),
      adminCompletionRequired: _boolValue(
        j['admin_completion_required'] ?? attrs['admin_completion_required'],
        fallback: '${j['status'] ?? 'draft'}' != 'completed',
      ),
    );
  }
}

class ProductMetaData {
  const ProductMetaData({required this.categories});
  final List<Map<String, dynamic>> categories;
  factory ProductMetaData.fromJson(Map<String, dynamic> j) => ProductMetaData(
        categories: ((j['categories'] as List?) ?? const [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
      );
}
