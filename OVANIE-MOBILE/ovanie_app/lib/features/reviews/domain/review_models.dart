import '../../../core/config/app_config.dart';
import '../../../core/utils/text_cleaner.dart';

int _int(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
DateTime? _date(dynamic value) {
  final raw = '${value ?? ''}'.trim();
  return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
}

class ExistingReview {
  final int id;
  final int rating;
  final String comment;
  final DateTime? updatedAt;
  const ExistingReview({required this.id, required this.rating, required this.comment, required this.updatedAt});
  factory ExistingReview.fromJson(Map<String, dynamic> json) => ExistingReview(
        id: _int(json['id']),
        rating: _int(json['rating']),
        comment: cleanOvanieText('${json['comment'] ?? ''}'),
        updatedAt: _date(json['updated_at']),
      );
}

class ProductReviewItem {
  final int orderId;
  final String orderNumber;
  final int orderItemId;
  final int productId;
  final String productName;
  final String productImageUrl;
  final DateTime? deliveredAt;
  final ExistingReview? review;
  const ProductReviewItem({
    required this.orderId,
    required this.orderNumber,
    required this.orderItemId,
    required this.productId,
    required this.productName,
    required this.productImageUrl,
    required this.deliveredAt,
    required this.review,
  });

  factory ProductReviewItem.fromJson(Map<String, dynamic> json) {
    final product = json['product'] is Map ? Map<String, dynamic>.from(json['product'] as Map) : <String, dynamic>{};
    final rawReview = json['review'];
    return ProductReviewItem(
      orderId: _int(json['order_id']),
      orderNumber: '${json['order_number'] ?? ''}',
      orderItemId: _int(json['order_item_id']),
      productId: _int(product['id']),
      productName: cleanOvanieText('${product['name'] ?? 'Produit OVANIE'}'),
      productImageUrl: AppConfig.normalizeMediaUrl('${product['main_image_url'] ?? ''}'),
      deliveredAt: _date(json['delivered_at']),
      review: rawReview is Map ? ExistingReview.fromJson(Map<String, dynamic>.from(rawReview)) : null,
    );
  }
}

class DeliveryReviewItem {
  final int orderId;
  final String orderNumber;
  final DateTime? deliveredAt;
  final ExistingReview? review;
  const DeliveryReviewItem({required this.orderId, required this.orderNumber, required this.deliveredAt, required this.review});
  factory DeliveryReviewItem.fromJson(Map<String, dynamic> json) {
    final rawReview = json['review'];
    return DeliveryReviewItem(
      orderId: _int(json['order_id']),
      orderNumber: '${json['order_number'] ?? ''}',
      deliveredAt: _date(json['delivered_at']),
      review: rawReview is Map ? ExistingReview.fromJson(Map<String, dynamic>.from(rawReview)) : null,
    );
  }
}

class ReviewCenterData {
  final List<ProductReviewItem> products;
  final List<DeliveryReviewItem> deliveries;
  final bool productRating;
  final bool deliveryRating;
  final bool sellerRating;
  const ReviewCenterData({required this.products, required this.deliveries, required this.productRating, required this.deliveryRating, required this.sellerRating});

  factory ReviewCenterData.fromJson(Map<String, dynamic> json) {
    final productRaw = json['product_reviews'] is List ? json['product_reviews'] as List : const [];
    final deliveryRaw = json['delivery_reviews'] is List ? json['delivery_reviews'] as List : const [];
    final caps = json['capabilities'] is Map ? Map<String, dynamic>.from(json['capabilities'] as Map) : <String, dynamic>{};
    return ReviewCenterData(
      products: productRaw.whereType<Map>().map((e) => ProductReviewItem.fromJson(Map<String, dynamic>.from(e))).toList(growable: false),
      deliveries: deliveryRaw.whereType<Map>().map((e) => DeliveryReviewItem.fromJson(Map<String, dynamic>.from(e))).toList(growable: false),
      productRating: caps['product_rating'] == true,
      deliveryRating: caps['delivery_rating'] == true,
      sellerRating: caps['seller_rating'] == true,
    );
  }
}
