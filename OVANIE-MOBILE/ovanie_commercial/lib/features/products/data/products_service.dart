import 'package:dio/dio.dart';
import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../models/product_data.dart';

class ProductsService {
  ProductsService(this.api, {this.missionId});
  final ApiClient api;
  final int? missionId;

  Map<String, dynamic> _query([Map<String, dynamic>? extra]) => {
        ...?extra,
        if (missionId != null) 'mission_id': missionId,
      };

  Map<String, dynamic> _body([Map<String, dynamic>? extra]) => {
        ...?extra,
        if (missionId != null) 'mission_id': missionId,
      };

  Future<List<CommercialShopOption>> shops({String q = ''}) async {
    final j = await api.getJson('/mobile/v1/commercial/products/shops', queryParameters: {'q': q});
    return ((j['shops'] as List?) ?? const [])
        .map((e) => CommercialShopOption.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<ProductMetaData> meta() async {
    final payload = await api.getJson('/mobile/v1/reference-data');
    final rawData = payload['data'];
    if (rawData is! Map) {
      throw const ApiException('Les référentiels OVANIE produit sont indisponibles.');
    }
    final data = Map<String, dynamic>.from(rawData);
    final categories = data['product_categories'] ?? data['categories'];
    if (categories is! List || categories.isEmpty) {
      throw const ApiException('Les catégories produit OVANIE sont indisponibles.');
    }
    return ProductMetaData.fromJson(<String, dynamic>{'categories': categories});
  }

  Future<List<CaptureSessionData>> sessions(int shopId) async {
    final j = await api.getJson(
      '/mobile/v1/commercial/products/sessions',
      queryParameters: _query({'shop_id': shopId}),
    );
    return ((j['sessions'] as List?) ?? const [])
        .map((e) => CaptureSessionData.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<CaptureSessionData> createSession({
    required int shopId,
    required String name,
    required String price,
    required String promoPrice,
    required String stock,
    required String weightKg,
    required String unit,
  }) async {
    final j = await api.postJson('/mobile/v1/commercial/products/sessions', body: _body({
      'shop_id': shopId,
      'name': name,
      'price': price,
      'promo_price': promoPrice.trim().isEmpty ? null : promoPrice,
      'stock': stock,
      'weight_kg': weightKg,
      'unit': unit,
    }));
    return CaptureSessionData.fromJson(Map<String, dynamic>.from(j['session'] as Map));
  }

  Future<Map<String, dynamic>> session(int id) => api.getJson(
        '/mobile/v1/commercial/products/sessions/$id',
        queryParameters: _query(),
      );

  Future<CapturedProductData> capture({required int sessionId, required String filePath}) async {
    final form = FormData.fromMap({
      'session_id': sessionId,
      if (missionId != null) 'mission_id': missionId,
      'photo': await MultipartFile.fromFile(filePath, filename: filePath.split('/').last),
    });
    final j = await api.postFormData('/mobile/v1/commercial/products/capture', formData: form);
    return CapturedProductData.fromJson(Map<String, dynamic>.from(j['product'] as Map));
  }

  Future<void> finishSession(int id) async {
    await api.postJson('/mobile/v1/commercial/products/sessions/$id/finish', body: _body());
  }

  Future<Map<String, dynamic>> incomplete(int sessionId) => api.getJson(
        '/mobile/v1/commercial/products/incomplete',
        queryParameters: _query({'session_id': sessionId}),
      );

  Future<Map<String, dynamic>> product(int productId) => api.getJson(
        '/mobile/v1/commercial/products/$productId/edit',
        queryParameters: _query(),
      );

  Future<Map<String, dynamic>> saveStep(int productId, int step, Map<String, dynamic> data) =>
      api.postJson('/mobile/v1/commercial/products/$productId/steps/$step', body: _body(data));

  Future<Map<String, dynamic>> uploadMedia(int productId, String filePath, {String kind = 'gallery'}) async {
    final form = FormData.fromMap({
      'kind': kind,
      if (missionId != null) 'mission_id': missionId,
      'media': await MultipartFile.fromFile(filePath, filename: filePath.split('/').last),
    });
    return api.postFormData('/mobile/v1/commercial/products/$productId/media', formData: form);
  }

  Future<Map<String, dynamic>> publish(int productId) =>
      api.postJson('/mobile/v1/commercial/products/$productId/publish', body: _body());
}
