import '../../../core/network/api_client.dart';
import '../domain/review_models.dart';

class ReviewsRepository {
  const ReviewsRepository();

  Future<ReviewCenterData> load() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/reviews');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map || body['data'] is! Map) {
      throw const OvanieApiException('Impossible de charger les avis OVANIE.');
    }
    return ReviewCenterData.fromJson(Map<String, dynamic>.from(body['data'] as Map));
  }

  Future<void> saveProduct({required int orderItemId, required int rating, required String comment}) async {
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/reviews/product', data: {
      'order_item_id': orderItemId,
      'rating': rating,
      'comment': comment.trim(),
    });
    ApiClient.ensureSuccess(response);
  }

  Future<void> saveDelivery({required int orderId, required int rating, required String comment}) async {
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/reviews/delivery', data: {
      'order_id': orderId,
      'rating': rating,
      'comment': comment.trim(),
    });
    ApiClient.ensureSuccess(response);
  }
}
