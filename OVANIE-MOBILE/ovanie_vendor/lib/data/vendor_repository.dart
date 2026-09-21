import 'dart:io';

import 'package:dio/dio.dart';

import '../core/config/app_config.dart';
import '../core/network/api_client.dart';
import '../core/reference_data/reference_data_store.dart';

class VendorRepository {
  VendorRepository._();
  static final VendorRepository instance = VendorRepository._();
  final Dio _dio = ApiClient.dio;
  final Map<int, Map<String, dynamic>> _productDetailCache = <int, Map<String, dynamic>>{};
  final Map<int, DateTime> _productDetailCacheAt = <int, DateTime>{};
  final Map<int, Map<String, dynamic>> _orderDetailCache = <int, Map<String, dynamic>>{};
  final Map<int, DateTime> _orderDetailCacheAt = <int, DateTime>{};

  void invalidateProductCache([int? id]) {
    if (id == null) {
      _productDetailCache.clear();
      _productDetailCacheAt.clear();
      return;
    }
    _productDetailCache.remove(id);
    _productDetailCacheAt.remove(id);
  }

  void invalidateOrderCache([int? id]) {
    if (id == null) {
      _orderDetailCache.clear();
      _orderDetailCacheAt.clear();
      return;
    }
    _orderDetailCache.remove(id);
    _orderDetailCacheAt.remove(id);
  }

  Future<Map<String, dynamic>> login(String identifier, String password) async {
    final response = await _dio.post('/mobile/v1/auth/login', data: {
      'email': identifier.trim(),
      'password': password,
      'device_name': 'OVANIE Vendeur Android',
      'portal': 'vendor',
    });
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<String> forgotPassword(String email) async {
    final response = await _dio.post('/mobile/v1/auth/forgot-password', data: {
      'email': email.trim().toLowerCase(),
      'portal': 'vendor',
    });
    ApiClient.ensureSuccess(response);
    final data = _map(response.data);
    final message = '${data['message'] ?? ''}'.trim();
    return message.isEmpty
        ? 'Si un compte correspond à ces informations, un lien de récupération a été envoyé.'
        : message;
  }


  Future<String> resetPassword({
    required String email,
    required String token,
    required String password,
    required String confirmation,
  }) async {
    final response = await _dio.post('/mobile/v1/auth/reset-password', data: {
      'email': email.trim().toLowerCase(),
      'token': token.trim(),
      'password': password,
      'password_confirmation': confirmation,
      'portal': 'vendor',
    });
    ApiClient.ensureSuccess(response);
    final data = _map(response.data);
    final message = '${data['message'] ?? ''}'.trim();
    return message.isEmpty
        ? 'Votre mot de passe OVANIE a été réinitialisé.'
        : message;
  }


  Future<void> logout() async {
    final response = await _dio.post('/mobile/v1/auth/logout');
    ApiClient.ensureSuccess(response);
  }

  Future<Map<String, dynamic>> me() async {
    final response = await _dio.get('/mobile/v1/auth/me');
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> context() async {
    final response = await _dio.get('/mobile/v1/vendor/context');
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  /// Reverse-geocoding du point GPS avec le même service que le Web /open-shop.
  Future<Map<String, dynamic>> reverseShopLocation(
    double latitude,
    double longitude,
  ) async {
    final response = await _dio.get(
      '${AppConfig.backendOrigin}/open-shop/geo/reverse',
      queryParameters: {'lat': latitude, 'lng': longitude},
      options: Options(headers: const {'Accept': 'application/json'}),
    );
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  /// Référentiels du formulaire d'ouverture boutique.
  ///
  /// Étape 7 : la source métier unique est
  /// GET /api/mobile/v1/reference-data. Aucun ancien endpoint meta n'est utilisé
  /// comme secours silencieux.
  Future<Map<String, dynamic>> meta() async {
    final references = OvanieReferenceDataStore.instance;
    await references.warmUp(force: true);

    final communes = references.records('communes');
    final categories = references.records('shop_categories');
    if (communes.isEmpty || categories.isEmpty) {
      throw const VendorApiException(
        'Les référentiels OVANIE de boutique sont indisponibles. Rechargez puis réessayez.',
      );
    }

    List<Map<String, String>> options(String key) => references
        .options(key)
        .map((item) => <String, String>{
              'value': item.code,
              'code': item.code,
              'label': item.label,
            })
        .toList(growable: false);

    for (final key in <String>[
      'seller_types',
      'identity_types',
      'logistics_types',
      'delivery_zones',
      'vendor_payment_modes',
      'mobile_money_operators',
    ]) {
      if (references.options(key).isEmpty) {
        throw VendorApiException(
          'Référentiel OVANIE manquant : $key. Rechargez puis réessayez.',
        );
      }
    }

    return <String, dynamic>{
      'communes': communes,
      'categories': categories,
      'shop_categories': categories,
      'regions': references.strings('regions'),
      'cities': references.strings('cities'),
      'seller_types': options('seller_types'),
      'legal_forms': options('legal_forms'),
      'identity_types': options('identity_types'),
      'logistics_types': options('logistics_types'),
      'delivery_zones': options('delivery_zones'),
      'payment_modes': options('vendor_payment_modes'),
      'mobile_money_operators': options('mobile_money_operators'),
      'identity_countries': references.records('identity_countries'),
      'processing_times': references.strings('processing_times'),
      'reference_source': 'reference-data',
    };
  }

  /// Quartiers/localités issus du même payload reference-data que les
  /// communes. Aucun endpoint meta parallèle n'est consulté.
  Future<List<dynamic>> quarters(int communeId, {String? communeName}) async {
    final references = OvanieReferenceDataStore.instance;
    await references.warmUp();
    final expectedName = (communeName ?? '').trim().toLowerCase();
    for (final commune in references.records('communes')) {
      final id = int.tryParse('${commune['id'] ?? ''}');
      final name = '${commune['name'] ?? ''}'.trim();
      final matchesId = communeId > 0 && id == communeId;
      final matchesName = expectedName.isNotEmpty &&
          name.toLowerCase() == expectedName;
      if (!matchesId && !matchesName) continue;
      return _list(commune['quarters']);
    }
    throw const VendorApiException(
      'Les quartiers OVANIE sont indisponibles pour cette commune.',
    );
  }

  /// Repères : même endpoint que le formulaire Web /open-shop.
  Future<List<dynamic>> landmarks({
    int? communeId,
    int? quarterId,
    String? communeName,
    String? quarterName,
  }) async {
    final commune = (communeName ?? '').trim();
    final quarter = (quarterName ?? '').trim();
    if (commune.isNotEmpty && quarter.isNotEmpty) {
      final response = await _dio.get(
        '${AppConfig.backendOrigin}/open-shop/geo/landmarks',
        queryParameters: {
          'commune': commune,
          'quarter': quarter,
          'limit': 50,
        },
        options: Options(headers: const {'Accept': 'application/json'}),
      );
      ApiClient.ensureSuccess(response);
      return _list(_map(response.data)['landmarks']);
    }

    final response = await _dio.get(
      '/mobile/v1/vendor/meta/landmarks',
      queryParameters: {
        if (communeId != null) 'commune_id': communeId,
        if (quarterId != null) 'quarter_id': quarterId,
      },
    );
    ApiClient.ensureSuccess(response);
    return _list(_map(response.data)['data']);
  }

  Future<Map<String, dynamic>> onboardShop(
    Map<String, dynamic> values, {
    required bool authenticated,
    File? selfie,
    File? identityDocument,
    File? identityFront,
    File? identityBack,
    File? rccmFile,
    File? taxFile,
  }) async {
    final data = Map<String, dynamic>.from(values);
    if (selfie != null) {
      data['selfie'] = await MultipartFile.fromFile(
        selfie.path,
        filename: selfie.uri.pathSegments.last,
      );
    }
    if (identityDocument != null) {
      data['identityFile'] = await MultipartFile.fromFile(
        identityDocument.path,
        filename: identityDocument.uri.pathSegments.last,
      );
    }
    if (identityFront != null) {
      data['identityFileFront'] = await MultipartFile.fromFile(
        identityFront.path,
        filename: identityFront.uri.pathSegments.last,
      );
    }
    if (identityBack != null) {
      data['identityFileBack'] = await MultipartFile.fromFile(
        identityBack.path,
        filename: identityBack.uri.pathSegments.last,
      );
    }
    if (rccmFile != null) {
      data['rccmFile'] = await MultipartFile.fromFile(
        rccmFile.path,
        filename: rccmFile.uri.pathSegments.last,
      );
    }
    if (taxFile != null) {
      data['taxFile'] = await MultipartFile.fromFile(
        taxFile.path,
        filename: taxFile.uri.pathSegments.last,
      );
    }

    // Même parcours métier que /open-shop sur le Web OVANIE :
    // un nouveau vendeur crée son compte vendeur ET sa boutique dans la
    // même opération. Un vendeur déjà connecté sans boutique utilise
    // l'endpoint authentifié et conserve son compte existant.
    // Endpoint unique pour l'ouverture de boutique.
    // Si un token Sanctum est déjà présent, Laravel l'utilise de manière
    // optionnelle et rattache la boutique au compte connecté. Sinon, Laravel
    // crée le compte vendeur et la boutique dans la même transaction.
    // Cela évite qu'une session existante bascule vers /shop/onboard et
    // rencontre un 404 alors que /open-shop est disponible.
    const endpoint = '/mobile/v1/vendor/open-shop-v2';
    final response = await _dio.post(endpoint, data: FormData.fromMap(data));
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> dashboard() async {
    final response = await _dio.get('/mobile/v1/vendor/dashboard');
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> shop() async {
    final response = await _dio.get('/mobile/v1/vendor/shop');
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> updateShop(Map<String, dynamic> values) async {
    final response = await _dio.patch('/mobile/v1/vendor/shop', data: values);
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> productMeta() async {
    final references = OvanieReferenceDataStore.instance;
    await references.warmUp();

    final categories = references.records('product_categories');
    if (categories.isEmpty) {
      throw const VendorApiException(
        'Les référentiels OVANIE produit sont indisponibles. Rechargez puis réessayez.',
      );
    }
    for (final key in <String>[
      'product_units',
      'product_types',
      'product_states',
      'selling_modes',
    ]) {
      if (references.options(key).isEmpty) {
        throw VendorApiException(
          'Référentiel OVANIE produit manquant : $key.',
        );
      }
    }

    List<Map<String, String>> options(String key) => references
        .options(key)
        .map((item) => <String, String>{
              'code': item.code,
              'value': item.code,
              'label': item.label,
            })
        .toList(growable: false);

    return <String, dynamic>{
      'product_categories': categories,
      'product_units': references.codes('product_units'),
      'product_unit_options': options('product_units'),
      'product_types': options('product_types'),
      'product_states': options('product_states'),
      'selling_modes': options('selling_modes'),
      'commercial_sale_types': options('commercial_sale_types'),
      'product_availability_statuses': options('product_availability_statuses'),
      'product_publication_statuses': options('product_publication_statuses'),
      'reference_source': 'reference-data',
    };
  }

  Future<Map<String, dynamic>> deliverySettings() async {
    final response = await _dio.get('/mobile/v1/vendor/shop/delivery');
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<void> updateDeliverySettings(Map<String, dynamic> values) async {
    final response = await _dio.post('/mobile/v1/vendor/shop/delivery', data: values);
    ApiClient.ensureSuccess(response);
  }

  Future<Map<String, dynamic>> products({String query = '', String status = 'all', String sort = 'newest', int page = 1, int perPage = 24}) async {
    final response = await _dio.get('/mobile/v1/vendor/products', queryParameters: {
      'page': page,
      if (query.trim().isNotEmpty) 'q': query.trim(),
      'status': status,
      'sort': sort,
      'per_page': perPage.clamp(10, 50),
    });
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> product(int id, {bool forceRefresh = false}) async {
    final cachedAt = _productDetailCacheAt[id];
    if (!forceRefresh && cachedAt != null && DateTime.now().difference(cachedAt) < const Duration(seconds: 45)) {
      final cached = _productDetailCache[id];
      if (cached != null) return Map<String, dynamic>.from(cached);
    }
    final response = await _dio.get('/mobile/v1/vendor/products/$id');
    ApiClient.ensureSuccess(response);
    final data = _map(response.data);
    _productDetailCache[id] = Map<String, dynamic>.from(data);
    _productDetailCacheAt[id] = DateTime.now();
    return data;
  }

  Future<void> saveProduct(
    Map<String, dynamic> values, {
    int? productId,
    List<File> images = const [],
    File? technicalSheet,
    File? video,
  }) async {
    final data = Map<String, dynamic>.from(values);
    for (var i = 0; i < images.length; i++) {
      data['images[$i]'] = await MultipartFile.fromFile(images[i].path, filename: images[i].uri.pathSegments.last);
    }
    if (technicalSheet != null) {
      data['technical_sheet'] = await MultipartFile.fromFile(technicalSheet.path, filename: technicalSheet.uri.pathSegments.last);
    }
    if (video != null) {
      data['product_video'] = await MultipartFile.fromFile(video.path, filename: video.uri.pathSegments.last);
    }
    final endpoint = productId == null ? '/mobile/v1/vendor/products' : '/mobile/v1/vendor/products/$productId';
    if (productId != null) data['_method'] = 'PATCH';
    final response = await _dio.post(endpoint, data: FormData.fromMap(data));
    ApiClient.ensureSuccess(response);
    if (productId != null) invalidateProductCache(productId);
    else invalidateProductCache();
  }

  Future<void> toggleProduct(int productId, bool active) async {
    final response = await _dio.post('/mobile/v1/vendor/products/$productId/toggle', data: {'is_active': active ? 1 : 0});
    ApiClient.ensureSuccess(response);
    invalidateProductCache(productId);
  }

  Future<void> archiveProduct(int productId) async {
    final response = await _dio.delete('/mobile/v1/vendor/products/$productId');
    ApiClient.ensureSuccess(response);
    invalidateProductCache(productId);
  }

  Future<void> restoreProduct(int productId) async {
    final response = await _dio.post('/mobile/v1/vendor/products/$productId/restore');
    ApiClient.ensureSuccess(response);
    invalidateProductCache(productId);
  }

  Future<Map<String, dynamic>> addProductImage(int productId, File image, {bool isMain = false}) async {
    final data = FormData.fromMap({
      'product_id': productId,
      'path': await MultipartFile.fromFile(image.path, filename: image.uri.pathSegments.last),
      'is_main': isMain ? 1 : 0,
    });
    final response = await _dio.post('/product-images', data: data);
    ApiClient.ensureSuccess(response);
    invalidateProductCache(productId);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> replaceProductImage(int imageId, File image) async {
    final data = FormData.fromMap({
      'path': await MultipartFile.fromFile(image.path, filename: image.uri.pathSegments.last),
    });
    final response = await _dio.patch('/product-images/$imageId', data: data);
    ApiClient.ensureSuccess(response);
    invalidateProductCache();
    return _map(response.data);
  }

  Future<Map<String, dynamic>> setMainProductImage(int imageId) async {
    final response = await _dio.patch('/product-images/$imageId', data: {'is_main': 1});
    ApiClient.ensureSuccess(response);
    invalidateProductCache();
    return _map(response.data);
  }

  Future<void> deleteProductImage(int imageId) async {
    final response = await _dio.delete('/product-images/$imageId');
    ApiClient.ensureSuccess(response);
    invalidateProductCache();
  }

  Future<void> reorderProductImages(int productId, List<int> imageIds) async {
    final response = await _dio.post('/mobile/v1/vendor/products/$productId/images/reorder', data: {'image_ids': imageIds});
    ApiClient.ensureSuccess(response);
    invalidateProductCache(productId);
  }

  Future<Map<String, dynamic>> orders({
    String query = '',
    String status = 'all',
    String sort = 'newest',
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _dio.get('/mobile/v1/vendor/orders', queryParameters: {
      'page': page,
      if (query.trim().isNotEmpty) 'q': query.trim(),
      'status': status,
      'sort': sort,
      'per_page': perPage.clamp(10, 50),
    });
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> order(int id, {bool fresh = false}) async {
    final cachedAt = _orderDetailCacheAt[id];
    if (!fresh && cachedAt != null && DateTime.now().difference(cachedAt) < const Duration(seconds: 20)) {
      final cached = _orderDetailCache[id];
      if (cached != null) return Map<String, dynamic>.from(cached);
    }
    final response = await _dio.get('/mobile/v1/vendor/orders/$id');
    ApiClient.ensureSuccess(response);
    final data = _map(response.data);
    _orderDetailCache[id] = Map<String, dynamic>.from(data);
    _orderDetailCacheAt[id] = DateTime.now();
    return data;
  }

  Future<void> updatePreparation(int orderId, String status, {String? note}) async {
    final response = await _dio.post('/mobile/v1/vendor/orders/$orderId/preparation', data: {
      'vendor_status': status,
      if (note?.trim().isNotEmpty == true) 'vendor_status_note': note!.trim(),
    });
    ApiClient.ensureSuccess(response);
    invalidateOrderCache(orderId);
  }

  Future<void> shipOrder(int orderId, Map<String, dynamic> values) async {
    final response = await _dio.post('/mobile/v1/vendor/orders/$orderId/ship', data: values);
    ApiClient.ensureSuccess(response);
    invalidateOrderCache(orderId);
  }

  Future<void> updateDeliveryStatus(int orderId, Map<String, dynamic> values) async {
    final response = await _dio.post('/mobile/v1/vendor/orders/$orderId/delivery-status', data: values);
    ApiClient.ensureSuccess(response);
    invalidateOrderCache(orderId);
  }

  Future<void> verifyOtp(int orderId, String code) async {
    final response = await _dio.post('/mobile/v1/vendor/orders/$orderId/verify-otp', data: {'delivery_otp_code': code});
    ApiClient.ensureSuccess(response);
    invalidateOrderCache(orderId);
  }

  Future<Map<String, dynamic>> finance() async {
    final response = await _dio.get('/mobile/v1/vendor/finance');
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> payouts({String status = 'all', int page = 1}) async {
    final response = await _dio.get('/mobile/v1/vendor/payouts', queryParameters: {'status': status, 'page': page, 'per_page': 20});
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<Map<String, dynamic>> payout(int id, {bool fresh = false}) async {
    final response = await _dio.get('/mobile/v1/vendor/payouts/$id');
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<void> requestPayoutFollowUp(int id, {String? note}) async {
    final response = await _dio.post('/mobile/v1/vendor/payouts/$id/follow-up', data: {
      if (note?.trim().isNotEmpty == true) 'vendor_note': note!.trim(),
    });
    ApiClient.ensureSuccess(response);
  }

  Future<Map<String, dynamic>> returns({int page = 1}) async {
    final response = await _dio.get('/mobile/v1/vendor/returns', queryParameters: {'page': page, 'per_page': 20});
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<void> decideReturn(int id, bool accept, {String? responseText}) async {
    final action = accept ? 'accept' : 'reject';
    final response = await _dio.post('/mobile/v1/vendor/returns/$id/$action', data: {
      if (responseText?.trim().isNotEmpty == true) 'vendor_response': responseText!.trim(),
    });
    ApiClient.ensureSuccess(response);
  }

  Future<Map<String, dynamic>> disputes({int page = 1}) async {
    final response = await _dio.get('/mobile/v1/vendor/disputes', queryParameters: {'page': page, 'per_page': 20});
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<void> respondDispute(int id, String text) async {
    final response = await _dio.post('/mobile/v1/vendor/disputes/$id/respond', data: {'response': text.trim()});
    ApiClient.ensureSuccess(response);
  }

  Future<void> escalateDispute(int id) async {
    final response = await _dio.post('/mobile/v1/vendor/disputes/$id/escalate');
    ApiClient.ensureSuccess(response);
  }

  Future<Map<String, dynamic>> notifications({int page = 1}) async {
    final response = await _dio.get('/mobile/v1/vendor/notifications', queryParameters: {'page': page, 'per_page': 30});
    ApiClient.ensureSuccess(response);
    return _map(response.data);
  }

  Future<void> markNotificationRead(String id) async {
    final response = await _dio.post('/mobile/v1/vendor/notifications/$id/read');
    ApiClient.ensureSuccess(response);
  }

  Future<void> markAllNotificationsRead() async {
    final response = await _dio.post('/mobile/v1/vendor/notifications/read-all');
    ApiClient.ensureSuccess(response);
  }

  static Map<String, dynamic> _map(dynamic raw) => raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
  static List<dynamic> _list(dynamic raw) => raw is List ? raw : const [];
}
