class CommercialShopSummary {
  const CommercialShopSummary({
    required this.all,
    required this.online,
    required this.pending,
    required this.suspended,
  });

  final int all;
  final int online;
  final int pending;
  final int suspended;

  static const empty = CommercialShopSummary(
    all: 0,
    online: 0,
    pending: 0,
    suspended: 0,
  );

  factory CommercialShopSummary.fromJson(dynamic json) {
    if (json is! Map) return empty;
    return CommercialShopSummary(
      all: _int(json['all']),
      online: _int(json['online']),
      pending: _int(json['pending']),
      suspended: _int(json['suspended']),
    );
  }
}

enum ShopFilter { all, online, pending, suspended }

extension ShopFilterApi on ShopFilter {
  String get apiValue => switch (this) {
        ShopFilter.all => 'all',
        ShopFilter.online => 'online',
        ShopFilter.pending => 'pending',
        ShopFilter.suspended => 'suspended',
      };
}

class CommercialShop {
  const CommercialShop({
    required this.id,
    required this.name,
    required this.displayName,
    required this.ownerName,
    required this.category,
    required this.subcategory,
    required this.city,
    required this.commune,
    required this.status,
    required this.statusLabel,
    required this.productCount,
    required this.orderCount,
    required this.sales30d,
    this.logoUrl,
    this.heroUrl,
  });

  final int id;
  final String name;
  final String displayName;
  final String ownerName;
  final String category;
  final String subcategory;
  final String city;
  final String commune;
  final String status;
  final String statusLabel;
  final int productCount;
  final int orderCount;
  final double sales30d;
  final String? logoUrl;
  final String? heroUrl;

  String get locationLabel {
    final parts = [city, commune].where((e) => e.trim().isNotEmpty).toList();
    return parts.isEmpty ? 'Localisation non renseignée' : parts.join(', ');
  }

  factory CommercialShop.fromJson(dynamic json) {
    if (json is! Map) {
      return const CommercialShop(
        id: 0,
        name: 'Boutique OVANIE',
        displayName: 'Boutique OVANIE',
        ownerName: '',
        category: '',
        subcategory: '',
        city: '',
        commune: '',
        status: 'pending',
        statusLabel: 'En attente',
        productCount: 0,
        orderCount: 0,
        sales30d: 0,
      );
    }

    final name = _string(json['name'], fallback: 'Boutique OVANIE');
    final display = _string(json['display_name'], fallback: name);
    return CommercialShop(
      id: _int(json['id']),
      name: name,
      displayName: display,
      ownerName: _string(json['owner_name']),
      category: _string(json['category']),
      subcategory: _string(json['subcategory']),
      city: _string(json['city']),
      commune: _string(json['commune']),
      status: _string(json['shop_status'], fallback: 'pending'),
      statusLabel: _string(json['shop_status_label'], fallback: 'En attente'),
      productCount: _int(json['products_count']),
      orderCount: _int(json['orders_count']),
      sales30d: _double(json['sales_30d']),
      logoUrl: _nullableUrl(json['logo_url']),
      heroUrl: _nullableUrl(json['hero_url']),
    );
  }
}

class CommercialShopsData {
  const CommercialShopsData({
    required this.profile,
    required this.unreadNotifications,
    required this.summary,
    required this.shops,
  });

  final Map<String, dynamic> profile;
  final int unreadNotifications;
  final CommercialShopSummary summary;
  final List<CommercialShop> shops;

  factory CommercialShopsData.fromJson(Map<String, dynamic> json) {
    final profileRaw = json['profile'];
    final shopsRaw = json['shops'];
    return CommercialShopsData(
      profile: profileRaw is Map
          ? Map<String, dynamic>.from(profileRaw)
          : const <String, dynamic>{},
      unreadNotifications: _int(json['unread_notifications']),
      summary: CommercialShopSummary.fromJson(json['summary']),
      shops: shopsRaw is List
          ? shopsRaw.map(CommercialShop.fromJson).toList(growable: false)
          : const <CommercialShop>[],
    );
  }
}

class ShopCategoryOption {
  const ShopCategoryOption({
    required this.id,
    required this.name,
    required this.slug,
    this.children = const [],
  });

  final int id;
  final String name;
  final String slug;
  final List<ShopCategoryOption> children;

  factory ShopCategoryOption.fromJson(dynamic json) {
    if (json is! Map) {
      return const ShopCategoryOption(id: 0, name: '', slug: '');
    }
    final childrenRaw = json['children'];
    return ShopCategoryOption(
      id: _int(json['id']),
      name: _string(json['name']),
      slug: _string(json['slug']),
      children: childrenRaw is List
          ? childrenRaw.map(ShopCategoryOption.fromJson).toList(growable: false)
          : const [],
    );
  }
}

class CommuneOption {
  const CommuneOption({required this.id, required this.name, this.quarters = const []});

  final int id;
  final String name;
  final List<QuarterOption> quarters;

  factory CommuneOption.fromJson(dynamic json) {
    if (json is! Map) return const CommuneOption(id: 0, name: '');
    final quartersRaw = json['quarters'];
    return CommuneOption(
      id: _int(json['id']),
      name: _string(json['name']),
      quarters: quartersRaw is List
          ? quartersRaw.map(QuarterOption.fromJson).toList(growable: false)
          : const [],
    );
  }
}

class QuarterOption {
  const QuarterOption({required this.id, required this.name});

  final int id;
  final String name;

  factory QuarterOption.fromJson(dynamic json) {
    if (json is! Map) return const QuarterOption(id: 0, name: '');
    return QuarterOption(id: _int(json['id']), name: _string(json['name']));
  }
}

class ShopMetaData {
  const ShopMetaData({
    required this.categories,
    required this.communes,
    required this.regions,
    required this.cities,
    required this.identityCountries,
    required this.identityTypes,
    required this.paymentModes,
    required this.payoutMethods,
    required this.logisticsTypes,
    required this.deliveryZones,
    required this.sellerTypes,
  });

  final List<ShopCategoryOption> categories;
  final List<CommuneOption> communes;
  final List<String> regions;
  final List<String> cities;
  final List<Map<String, String>> identityCountries;
  final List<Map<String, String>> identityTypes;
  final List<Map<String, String>> paymentModes;
  final List<Map<String, String>> payoutMethods;
  final List<Map<String, String>> logisticsTypes;
  final List<Map<String, String>> deliveryZones;
  final List<Map<String, String>> sellerTypes;

  factory ShopMetaData.fromJson(Map<String, dynamic> json) {
    List<Map<String, String>> mapList(dynamic raw) {
      if (raw is! List) return const [];
      return raw.whereType<Map>().map((item) {
        return item.map((key, value) => MapEntry(key.toString(), value?.toString() ?? ''));
      }).toList(growable: false);
    }

    return ShopMetaData(
      categories: (json['categories'] is List)
          ? (json['categories'] as List)
              .map(ShopCategoryOption.fromJson)
              .toList(growable: false)
          : const [],
      communes: (json['communes'] is List)
          ? (json['communes'] as List)
              .map(CommuneOption.fromJson)
              .toList(growable: false)
          : const [],
      regions: (json['regions'] is List)
          ? (json['regions'] as List).map((e) => e.toString()).toList(growable: false)
          : const <String>[],
      cities: (json['cities'] is List)
          ? (json['cities'] as List).map((e) => e.toString()).toList(growable: false)
          : const <String>[],
      identityCountries: mapList(json['identity_countries']),
      identityTypes: mapList(json['identity_types']),
      paymentModes: mapList(json['payment_modes']),
      payoutMethods: mapList(json['payout_methods']),
      logisticsTypes: mapList(json['logistics_types']),
      deliveryZones: mapList(json['delivery_zones']),
      sellerTypes: mapList(json['seller_types']),
    );
  }
}

class ShopPublicCategory {
  const ShopPublicCategory({
    required this.id,
    required this.name,
    required this.productCount,
    this.imageUrl,
  });

  final int id;
  final String name;
  final int productCount;
  final String? imageUrl;

  factory ShopPublicCategory.fromJson(dynamic json) {
    if (json is! Map) {
      return const ShopPublicCategory(id: 0, name: '', productCount: 0);
    }
    return ShopPublicCategory(
      id: _int(json['id']),
      name: _string(json['name']),
      productCount: _int(json['products_count']),
      imageUrl: _nullableUrl(json['image_url']),
    );
  }
}

class ShopPublicProduct {
  const ShopPublicProduct({
    required this.id,
    required this.name,
    required this.price,
    required this.oldPrice,
    required this.discountPercent,
    this.imageUrl,
  });

  final int id;
  final String name;
  final double price;
  final double oldPrice;
  final int discountPercent;
  final String? imageUrl;

  factory ShopPublicProduct.fromJson(dynamic json) {
    if (json is! Map) {
      return const ShopPublicProduct(
        id: 0,
        name: '',
        price: 0,
        oldPrice: 0,
        discountPercent: 0,
      );
    }
    return ShopPublicProduct(
      id: _int(json['id']),
      name: _string(json['name']),
      price: _double(json['price']),
      oldPrice: _double(json['old_price']),
      discountPercent: _int(json['discount_percent']),
      imageUrl: _nullableUrl(json['image_url']),
    );
  }
}

class CommercialShopDetail {
  const CommercialShopDetail({
    required this.shop,
    required this.description,
    required this.rating,
    required this.reviewsCount,
    required this.followersCount,
    required this.categories,
    required this.products,
    required this.logisticsLabel,
    required this.payoutLabel,
    required this.ownerPhone,
    required this.ownerEmail,
  });

  final CommercialShop shop;
  final String description;
  final double rating;
  final int reviewsCount;
  final int followersCount;
  final List<ShopPublicCategory> categories;
  final List<ShopPublicProduct> products;
  final String logisticsLabel;
  final String payoutLabel;
  final String ownerPhone;
  final String ownerEmail;

  factory CommercialShopDetail.fromJson(Map<String, dynamic> json) {
    final shopRaw = json['shop'];
    final categoriesRaw = json['categories'];
    final productsRaw = json['products'];
    return CommercialShopDetail(
      shop: CommercialShop.fromJson(shopRaw),
      description: _string(json['description']),
      rating: _double(json['rating']),
      reviewsCount: _int(json['reviews_count']),
      followersCount: _int(json['followers_count']),
      categories: categoriesRaw is List
          ? categoriesRaw.map(ShopPublicCategory.fromJson).toList(growable: false)
          : const [],
      products: productsRaw is List
          ? productsRaw.map(ShopPublicProduct.fromJson).toList(growable: false)
          : const [],
      logisticsLabel: _string(json['logistics_label'], fallback: 'OVANIE Logistics'),
      payoutLabel: _string(json['payout_label']),
      ownerPhone: _string(json['owner_phone']),
      ownerEmail: _string(json['owner_email']),
    );
  }
}

class CreateCommercialShopResult {
  const CreateCommercialShopResult({
    required this.message,
    required this.shop,
  });

  final String message;
  final CommercialShop shop;

  factory CreateCommercialShopResult.fromJson(Map<String, dynamic> json) {
    return CreateCommercialShopResult(
      message: _string(json['message'], fallback: 'Boutique créée avec succès.'),
      shop: CommercialShop.fromJson(json['shop']),
    );
  }
}

class ReverseGeocodeResult {
  const ReverseGeocodeResult({this.address, this.commune, this.quarter, this.landmark, this.city});

  final String? address;
  final String? commune;
  final String? quarter;
  final String? landmark;
  final String? city;

  factory ReverseGeocodeResult.fromJson(dynamic json) {
    if (json is! Map) return const ReverseGeocodeResult();
    return ReverseGeocodeResult(
      address: _nullableUrl(json['address']),
      commune: _nullableUrl(json['commune']),
      quarter: _nullableUrl(json['quarter']),
      landmark: _nullableUrl(json['landmark']),
      city: _nullableUrl(json['city']),
    );
  }
}

int _int(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

double _double(dynamic value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

String _string(dynamic value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

String? _nullableUrl(dynamic value) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? null : text;
}
