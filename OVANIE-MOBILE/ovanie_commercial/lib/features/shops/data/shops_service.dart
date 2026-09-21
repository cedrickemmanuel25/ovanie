import 'package:dio/dio.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../models/shop_data.dart';

class ShopsService {
  ShopsService(this._api);

  final ApiClient _api;

  Future<CommercialShopsData> fetch({
    String query = '',
    ShopFilter filter = ShopFilter.all,
  }) async {
    final json = await _api.getJson(
      '/mobile/v1/commercial/shops',
      queryParameters: {
        if (query.trim().isNotEmpty) 'q': query.trim(),
        'status': filter.apiValue,
      },
    );
    return CommercialShopsData.fromJson(json);
  }

  Future<ShopMetaData> meta() async {
    final payload = await _api.getJson('/mobile/v1/reference-data');
    final rawData = payload['data'];
    if (rawData is! Map) {
      throw const ApiException(
        'Les référentiels OVANIE boutique sont indisponibles.',
      );
    }
    final data = Map<String, dynamic>.from(rawData);
    for (final key in <String>[
      'communes',
      'seller_types',
      'identity_types',
      'logistics_types',
      'delivery_zones',
      'vendor_payment_modes',
    ]) {
      final value = data[key];
      if (value is! List || value.isEmpty) {
        throw ApiException('Référentiel OVANIE manquant : $key.');
      }
    }
    return ShopMetaData.fromJson(<String, dynamic>{
      'categories': data['shop_categories'] ?? data['categories'],
      'communes': data['communes'],
      'regions': data['regions'],
      'cities': data['cities'],
      'identity_countries': data['identity_countries'],
      'identity_types': data['identity_types'],
      'payment_modes': data['vendor_payment_modes'],
      'payout_methods': data['payout_methods'],
      'logistics_types': data['logistics_types'],
      'delivery_zones': data['delivery_zones'],
      'seller_types': data['seller_types'],
    });
  }

  Future<ReverseGeocodeResult> reverseGeocode({required double latitude, required double longitude}) async {
    final json = await _api.getJson(
      '/mobile/v1/commercial/geo/reverse',
      queryParameters: {'latitude': latitude, 'longitude': longitude},
    );
    return ReverseGeocodeResult.fromJson(json);
  }

  Future<CommercialShopDetail> detail(int shopId) async {
    final json = await _api.getJson('/mobile/v1/commercial/shops/$shopId');
    return CommercialShopDetail.fromJson(json);
  }

  Future<CreateCommercialShopResult> create({
    required Map<String, dynamic> fields,
    required Map<String, String?> files,
  }) async {
    final form = FormData();

    for (final entry in fields.entries) {
      final value = entry.value;
      if (value == null) continue;
      if (value is bool) {
        form.fields.add(MapEntry(entry.key, value ? '1' : '0'));
      } else {
        final text = value.toString().trim();
        if (text.isNotEmpty) form.fields.add(MapEntry(entry.key, text));
      }
    }

    for (final entry in files.entries) {
      final path = entry.value?.trim() ?? '';
      if (path.isEmpty) continue;
      form.files.add(
        MapEntry(
          entry.key,
          await MultipartFile.fromFile(path, filename: path.split(RegExp(r'[/\\]')).last),
        ),
      );
    }

    final json = await _api.postFormData(
      '/mobile/v1/commercial/shops',
      formData: form,
    );
    return CreateCommercialShopResult.fromJson(json);
  }
}
