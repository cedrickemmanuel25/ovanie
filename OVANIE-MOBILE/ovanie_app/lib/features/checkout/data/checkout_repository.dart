import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../domain/checkout_models.dart';

class CheckoutRepository {
  const CheckoutRepository();

  Future<List<CheckoutSavedAddress>> savedAddresses() async {
    final response = await ApiClient.dio.get<dynamic>('/addresses');
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map || body['data'] is! List) return const [];

    return (body['data'] as List)
        .whereType<Map>()
        .map(
          (item) => CheckoutSavedAddress.fromJson(
            Map<String, dynamic>.from(item),
          ),
        )
        .where((address) => address.id > 0)
        .toList(growable: false);
  }

  Future<CheckoutPreview> preview(CheckoutAddress input) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/checkout/preview',
      data: input.toJson(),
      options: Options(
        sendTimeout: const Duration(seconds: 30),
        receiveTimeout: const Duration(seconds: 45),
      ),
    );
    ApiClient.ensureSuccess(response);

    final data = response.data;
    if (data is! Map) {
      throw const OvanieApiException(
        'Le calcul de la livraison OVANIE est momentanément indisponible.',
      );
    }
    return CheckoutPreview.fromJson(Map<String, dynamic>.from(data));
  }

  Future<CheckoutOrderResult> placeOrder(CheckoutAddress input) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/orders',
      data: input.toJson(),
      options: Options(
        sendTimeout: const Duration(seconds: 45),
        receiveTimeout: const Duration(seconds: 120),
      ),
    );
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map || body['data'] is! Map || body['payment'] is! Map) {
      throw const OvanieApiException(
        'La commande OVANIE retournée par le serveur est invalide.',
      );
    }

    final data = body['data'] as Map;
    final payment = body['payment'] as Map;
    return CheckoutOrderResult(
      orderId: int.tryParse('${data['id'] ?? ''}') ?? 0,
      orderNumber: (data['order_number'] ?? data['number'] ?? '').toString(),
      paymentStatus: (payment['status'] ?? '').toString(),
      paymentMethod: (payment['method'] ?? input.paymentMethod).toString(),
      paymentAmount: payment['amount'] is num
          ? (payment['amount'] as num).toDouble()
          : double.tryParse('${payment['amount'] ?? ''}') ?? 0,
      paymentUrl: (payment['payment_url'] ?? '').toString(),
      paymentOperator: (payment['operator'] ?? input.onlineOperator).toString(),
    );
  }

  Future<CheckoutOrderResult> startOnlinePayment({
    required int orderId,
    required String operator,
    required String phone,
    String orangeOtp = '',
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/orders',
      data: <String, dynamic>{
        'existing_order_id': orderId,
        'payment_method': 'paydunya',
        'online_operator': operator,
        'payment_phone': operator == 'card' ? null : phone.trim(),
        'orange_otp': orangeOtp.trim().isEmpty ? null : orangeOtp.trim(),
      },
      options: Options(
        sendTimeout: const Duration(seconds: 45),
        receiveTimeout: const Duration(seconds: 120),
      ),
    );
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map || body['data'] is! Map || body['payment'] is! Map) {
      throw const OvanieApiException(
        'Le paiement OVANIE retourné par le serveur est invalide.',
      );
    }

    final data = body['data'] as Map;
    final payment = body['payment'] as Map;
    return CheckoutOrderResult(
      orderId: int.tryParse('${data['id'] ?? orderId}') ?? orderId,
      orderNumber: (data['order_number'] ?? data['number'] ?? '').toString(),
      paymentStatus: (payment['status'] ?? '').toString(),
      paymentMethod: (payment['method'] ?? 'paydunya').toString(),
      paymentAmount: payment['amount'] is num
          ? (payment['amount'] as num).toDouble()
          : double.tryParse('${payment['amount'] ?? ''}') ?? 0,
      paymentUrl: (payment['payment_url'] ?? '').toString(),
      paymentOperator: (payment['operator'] ?? operator).toString(),
    );
  }

}
