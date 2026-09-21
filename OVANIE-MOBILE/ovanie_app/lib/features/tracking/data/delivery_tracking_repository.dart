import '../../../core/network/api_client.dart';
import '../domain/delivery_tracking_model.dart';

class DeliveryTrackingRepository {
  const DeliveryTrackingRepository();

  Future<ClientDeliveryTracking?> fetchOrderTracking(int orderId) async {
    final response = await ApiClient.dio.get<dynamic>('/orders/$orderId/tracking');
    final status = response.statusCode ?? 0;

    // Une commande payée peut être confirmée avant que la logistique n'ait
    // créé sa mission. Ce n'est pas une erreur côté client : l'écran affiche
    // alors simplement "Préparation en cours".
    if (status == 404) return null;
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map) return null;
    return ClientDeliveryTracking.fromJson(Map<String, dynamic>.from(body));
  }
}
