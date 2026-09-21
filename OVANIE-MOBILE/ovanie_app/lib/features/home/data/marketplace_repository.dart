import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../categories/domain/category_model.dart';
import '../../products/domain/product_model.dart';

class MarketplaceRepository {
  const MarketplaceRepository();

  Future<List<CategoryModel>> getAllCategories({bool activeOnly = true}) async {
    final response = await ApiClient.dio.get(
      '/categories',
      queryParameters: {if (activeOnly) 'active': 1},
    );
    ApiClient.ensureSuccess(response);
    return _parseCategories(response.data);
  }

  Future<List<CategoryModel>> getRootCategories({int limit = 50}) async {
    var response = await ApiClient.dio.get('/marketplace/categories');

    // Certaines installations du backend exposent encore uniquement la route
    // historique. Ce repli permet a l'application mobile de rester compatible
    // pendant le deploiement progressif des nouvelles routes marketplace.
    if (response.statusCode == 404) {
      response = await ApiClient.dio.get(
        '/categories',
        queryParameters: const {'active': 1},
      );
    }

    ApiClient.ensureSuccess(response);
    return _parseCategories(response.data).take(limit).toList();
  }

  Future<HomeMarketplaceData> getHomeData() async {
    final response = await ApiClient.dio.get('/marketplace/home');
    ApiClient.ensureSuccess(response);

    final root = _asMap(response.data);
    final data = _asMap(root['data']);

    final categories = _parseCategories(data['categories']);
    final rawSections = data['sections'];
    final sections = rawSections is List
        ? rawSections
            .whereType<Map>()
            .map((item) => HomeMarketplaceSection.fromJson(Map<String, dynamic>.from(item)))
            .where((section) => section.products.isNotEmpty)
            .toList()
        : <HomeMarketplaceSection>[];

    final receivedAtUtc = DateTime.now().toUtc();
    final serverTimeUtc = _parseDateTime(data['server_time']) ?? receivedAtUtc;
    final flashSaleEndsAtUtc = _parseDateTime(data['flash_sale_ends_at']);
    final blackFridayMaxDiscountPercent =
        _toInt(data['black_friday_max_discount_percent']);
    final blackFridayProductCount = _toInt(data['black_friday_product_count']);

    return HomeMarketplaceData(
      categories: categories,
      sections: sections,
      source: data['source']?.toString() ?? 'laravel',
      serverTimeUtc: serverTimeUtc,
      receivedAtUtc: receivedAtUtc,
      flashSaleEndsAtUtc: flashSaleEndsAtUtc,
      blackFridayMaxDiscountPercent: blackFridayMaxDiscountPercent,
      blackFridayProductCount: blackFridayProductCount,
    );
  }

  Future<SearchSuggestions> getSearchSuggestions(
    String query, {
    String? categorySlug,
    int limit = 8,
  }) async {
    final term = query.trim();
    if (term.length < 2) return const SearchSuggestions.empty();

    final response = await ApiClient.dio.get(
      '/search',
      queryParameters: {
        'q': term,
        if (categorySlug != null && categorySlug.trim().isNotEmpty)
          'category': categorySlug.trim(),
        'limit': limit.clamp(1, 10),
      },
    );
    ApiClient.ensureSuccess(response);

    final data = _asMap(response.data);
    final productsRaw = data['products'];
    final categoriesRaw = data['categories'];
    final popularRaw = data['popular'];

    return SearchSuggestions(
      query: data['query']?.toString() ?? term,
      products: productsRaw is List
          ? productsRaw
              .whereType<Map>()
              .map((item) => SearchProductSuggestion.fromJson(Map<String, dynamic>.from(item)))
              .toList()
          : const [],
      categories: categoriesRaw is List
          ? categoriesRaw
              .whereType<Map>()
              .map((item) => SearchCategorySuggestion.fromJson(Map<String, dynamic>.from(item)))
              .toList()
          : const [],
      popular: popularRaw is List
          ? popularRaw.map((item) => item.toString()).where((item) => item.trim().isNotEmpty).toList()
          : const [],
    );
  }

  Future<CatalogFilters> getCatalogFilters() async {
    final response = await ApiClient.dio.get('/marketplace/filters');
    ApiClient.ensureSuccess(response);
    final root = _asMap(response.data);
    return CatalogFilters.fromJson(_asMap(root['data']));
  }

  Future<CatalogPage> getCatalogPage({
    String? categorySlug,
    String? query,
    String? brand,
    String? stock,
    String? offer,
    double? minPrice,
    double? maxPrice,
    String sort = 'popular',
    int page = 1,
    int perPage = 20,
  }) async {
    final params = <String, dynamic>{
      'sort': sort,
      'page': page.clamp(1, 1000000),
      'per_page': perPage.clamp(12, 48),
    };

    if (categorySlug != null && categorySlug.trim().isNotEmpty) {
      params['category'] = categorySlug.trim();
    }
    if (query != null && query.trim().isNotEmpty) {
      params['search'] = query.trim();
    }
    if (brand != null && brand.trim().isNotEmpty) {
      params['brand'] = brand.trim();
    }
    if (stock != null && stock.trim().isNotEmpty) {
      params['stock'] = stock.trim();
    }
    if (offer != null && offer.trim().isNotEmpty) {
      params['offer'] = offer.trim();
    }
    if (minPrice != null && minPrice >= 0) params['min_price'] = minPrice;
    if (maxPrice != null && maxPrice >= 0) params['max_price'] = maxPrice;

    final response = await ApiClient.dio.get('/products', queryParameters: params);
    ApiClient.ensureSuccess(response);

    final data = _asMap(response.data);
    final products = _parseProducts(data['data']);

    return CatalogPage(
      products: products,
      currentPage: _toInt(data['current_page'], fallback: page),
      lastPage: _toInt(data['last_page'], fallback: 1),
      perPage: _toInt(data['per_page'], fallback: perPage),
      total: _toInt(data['total']),
    );
  }

  Future<List<ProductModel>> getCatalog({
    String? categorySlug,
    String? query,
    int limit = 24,
    String sort = 'popular',
  }) async {
    final page = await getCatalogPage(
      categorySlug: categorySlug,
      query: query,
      perPage: _apiPerPage(limit),
      sort: sort,
    );
    return page.products.take(limit).toList();
  }

  Future<ProductModel> getProductBySlug(String productSlug) async {
    final slug = productSlug.trim();
    if (slug.isEmpty) {
      throw const OvanieApiException('La référence publique de ce produit est invalide.');
    }

    try {
      final response = await ApiClient.dio.get('/products/$slug');
      ApiClient.ensureSuccess(response);
      final root = _asMap(response.data);
      final data = root['data'];
      if (data is Map) {
        return ProductModel.fromJson(Map<String, dynamic>.from(data));
      }
    } catch (_) {
      // Compatibilité avec les anciens backends : fallback catalogue.
    }

    final page = await getCatalogPage(query: slug, perPage: 16, sort: 'recent');
    for (final product in page.products) {
      if (product.slug.trim().toLowerCase() == slug.toLowerCase()) return product;
    }

    throw const OvanieApiException('Ce produit n’est plus disponible dans le catalogue OVANIE.');
  }

  Future<List<ProductModel>> getLatestProducts({int limit = 12}) async {
    final page = await getCatalogPage(sort: 'recent', perPage: _apiPerPage(limit));
    return page.products.take(limit).toList();
  }

  Future<List<ProductModel>> getBestSellers({int limit = 12}) async {
    final response = await ApiClient.dio.get(
      '/products/top-sellers',
      queryParameters: {'limit': limit.clamp(1, 60)},
    );
    ApiClient.ensureSuccess(response);
    return _parseProducts(response.data).take(limit).toList();
  }

  Future<List<ProductModel>> getRecommendations({int limit = 12}) async {
    final response = await ApiClient.dio.get(
      '/products/recommendations',
      queryParameters: {'limit': limit.clamp(1, 60)},
    );
    ApiClient.ensureSuccess(response);
    return _parseProducts(response.data).take(limit).toList();
  }

  CategoryModel? findCategoryInRoots(
    List<CategoryModel> roots, {
    int? id,
    String? slug,
  }) {
    final targetSlug = slug?.toLowerCase().trim();
    for (final root in roots) {
      if ((id != null && id > 0 && root.id == id) ||
          (targetSlug != null && _slugEquivalent(root.slug, targetSlug))) {
        return root;
      }
      for (final child in root.children) {
        if ((id != null && id > 0 && child.id == id) ||
            (targetSlug != null && _slugEquivalent(child.slug, targetSlug))) {
          return child;
        }
      }
    }
    return null;
  }

  CategoryModel? findRootForCategory(List<CategoryModel> roots, CategoryModel category) {
    if (category.parentId == null) return category;
    for (final root in roots) {
      if (root.id > 0 && root.id == category.parentId) return root;
      if (root.children.any((child) => child.id > 0 && child.id == category.id)) {
        return root;
      }
    }
    return null;
  }

  bool _slugEquivalent(String a, String b) {
    return a.toLowerCase().trim() == b.toLowerCase().trim();
  }

  int _apiPerPage(int requested) {
    if (requested <= 16) return 16;
    if (requested <= 24) return 24;
    if (requested <= 32) return 32;
    return 48;
  }

  Future<List<ProductModel>> safeProducts(Future<List<ProductModel>> Function() request) async {
    try {
      return await request();
    } on DioException {
      return const [];
    } on OvanieApiException {
      return const [];
    }
  }

  static Map<String, dynamic> _asMap(dynamic data) {
    if (data is Map) return Map<String, dynamic>.from(data);
    return <String, dynamic>{};
  }

  List<CategoryModel> _parseCategories(dynamic data) {
    final items = _extractList(data);
    return items
        .whereType<Map>()
        .map((item) => CategoryModel.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  static List<ProductModel> _parseProducts(dynamic data) {
    final items = _extractList(data);
    return items
        .whereType<Map>()
        .map((item) => ProductModel.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  static List<dynamic> _extractList(dynamic data) {
    if (data is List) return data;
    if (data is Map) {
      final direct = data['data'];
      if (direct is List) return direct;
      if (direct is Map && direct['data'] is List) return direct['data'] as List;
    }
    return const [];
  }

  static int _toInt(dynamic value, {int fallback = 0}) {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static DateTime? _parseDateTime(dynamic value) {
    final raw = value?.toString().trim() ?? '';
    if (raw.isEmpty) return null;
    return DateTime.tryParse(raw)?.toUtc();
  }
}

class HomeMarketplaceData {
  final List<CategoryModel> categories;
  final List<HomeMarketplaceSection> sections;
  final String source;
  final DateTime serverTimeUtc;
  final DateTime receivedAtUtc;
  final DateTime? flashSaleEndsAtUtc;
  final int blackFridayMaxDiscountPercent;
  final int blackFridayProductCount;

  const HomeMarketplaceData({
    required this.categories,
    required this.sections,
    required this.source,
    required this.serverTimeUtc,
    required this.receivedAtUtc,
    required this.flashSaleEndsAtUtc,
    required this.blackFridayMaxDiscountPercent,
    required this.blackFridayProductCount,
  });

  /// Décalage entre l'heure du téléphone et l'heure Laravel au moment de la
  /// réponse. Le chrono Flash reste donc synchronisé avec le Web même si
  /// l'horloge du téléphone est légèrement fausse.
  Duration get serverClockOffset => serverTimeUtc.difference(receivedAtUtc);
}

class HomeMarketplaceSection {
  final String key;
  final String mode;
  final String title;
  final String? catalogOffer;
  final List<ProductModel> products;

  const HomeMarketplaceSection({
    required this.key,
    required this.mode,
    required this.title,
    required this.catalogOffer,
    required this.products,
  });

  factory HomeMarketplaceSection.fromJson(Map<String, dynamic> json) {
    return HomeMarketplaceSection(
      key: json['key']?.toString() ?? '',
      mode: json['mode']?.toString() ?? '',
      title: json['title']?.toString() ?? 'Produits OVANIE',
      catalogOffer: json['catalog_offer']?.toString(),
      products: MarketplaceRepository._parseProducts(json['products']),
    );
  }
}

class CatalogPage {
  final List<ProductModel> products;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  const CatalogPage({
    required this.products,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  bool get hasMore => currentPage < lastPage;
}

class CatalogFilters {
  final List<String> brands;
  final double minPrice;
  final double maxPrice;
  final List<CatalogOption> availability;
  final List<CatalogOption> sorts;

  const CatalogFilters({
    required this.brands,
    required this.minPrice,
    required this.maxPrice,
    required this.availability,
    required this.sorts,
  });

  factory CatalogFilters.fromJson(Map<String, dynamic> json) {
    final price = MarketplaceRepository._asMap(json['price']);
    final rawBrands = json['brands'];
    return CatalogFilters(
      brands: rawBrands is List
          ? rawBrands.map((item) => item.toString()).where((item) => item.trim().isNotEmpty).toList()
          : const [],
      minPrice: double.tryParse('${price['min'] ?? 0}') ?? 0,
      maxPrice: double.tryParse('${price['max'] ?? 0}') ?? 0,
      availability: _parseOptions(json['availability']),
      sorts: _parseOptions(json['sorts']),
    );
  }

  static List<CatalogOption> _parseOptions(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((item) => CatalogOption.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }
}

class CatalogOption {
  final String value;
  final String label;

  const CatalogOption({required this.value, required this.label});

  factory CatalogOption.fromJson(Map<String, dynamic> json) {
    return CatalogOption(
      value: json['value']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
    );
  }
}

class SearchSuggestions {
  final String query;
  final List<SearchProductSuggestion> products;
  final List<SearchCategorySuggestion> categories;
  final List<String> popular;

  const SearchSuggestions({
    required this.query,
    required this.products,
    required this.categories,
    required this.popular,
  });

  const SearchSuggestions.empty()
      : query = '',
        products = const [],
        categories = const [],
        popular = const [];
}

class SearchProductSuggestion {
  final int id;
  final String name;
  final String slug;
  final String brand;
  final String imageUrl;
  final double price;
  final String categoryName;

  const SearchProductSuggestion({
    required this.id,
    required this.name,
    required this.slug,
    required this.brand,
    required this.imageUrl,
    required this.price,
    required this.categoryName,
  });

  factory SearchProductSuggestion.fromJson(Map<String, dynamic> json) {
    final category = MarketplaceRepository._asMap(json['category']);
    return SearchProductSuggestion(
      id: MarketplaceRepository._toInt(json['id']),
      name: json['name']?.toString() ?? 'Produit OVANIE',
      slug: json['slug']?.toString() ?? '',
      brand: json['brand']?.toString() ?? '',
      imageUrl: json['image_url']?.toString() ?? '',
      price: double.tryParse('${json['price'] ?? 0}') ?? 0,
      categoryName: category['name']?.toString() ?? '',
    );
  }
}

class SearchCategorySuggestion {
  final int id;
  final String name;
  final String slug;

  const SearchCategorySuggestion({required this.id, required this.name, required this.slug});

  factory SearchCategorySuggestion.fromJson(Map<String, dynamic> json) {
    return SearchCategorySuggestion(
      id: MarketplaceRepository._toInt(json['id']),
      name: json['name']?.toString() ?? 'Catégorie',
      slug: json['slug']?.toString() ?? '',
    );
  }
}
