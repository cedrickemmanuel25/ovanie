import '../../../core/config/app_config.dart';
import '../../../core/utils/text_cleaner.dart';

class ProductModel {
  final int id;
  final String slug;
  final String name;
  final String shortDescription;
  final String description;
  final String technicalDetails;
  final Map<String, String> attributes;
  final Map<String, String> technicalSpecs;
  final String reference;
  final String productStateLabel;
  final String supplyDelay;
  final String returnPolicy;
  final String availabilityStatus;
  final String categoryName;
  final String categorySlug;
  final int categoryId;
  final String unitLabel;
  final String packaging;
  final String brand;
  final String usageArea;
  final String materialGrade;
  final String color;
  final String standard;
  final String warranty;
  final String originCountry;
  final double contentPerUnit;
  final String contentUnit;
  final int unitsPerPackage;
  final double coveragePerUnitM2;
  final double weightKg;
  final double lengthCm;
  final double widthCm;
  final double heightCm;
  final double volumeM3;
  final bool fragile;
  final bool requiresUnloading;
  final double price;
  final double finalPrice;
  final double? promoPrice;
  final double originalPublicPrice;
  final int discountPercent;
  final int stock;
  final int minOrderQuantity;
  final bool canAddToCart;
  final bool isNegotiable;
  final bool isOrderable;
  final String availabilityLabel;
  final double? rating;
  final int reviewsCount;
  final int sales;
  final String imageUrl;
  final List<String> galleryUrls;
  final List<String> tags;

  const ProductModel({
    required this.id,
    required this.slug,
    required this.name,
    required this.shortDescription,
    required this.description,
    required this.technicalDetails,
    required this.attributes,
    required this.technicalSpecs,
    required this.reference,
    required this.productStateLabel,
    required this.supplyDelay,
    required this.returnPolicy,
    required this.availabilityStatus,
    required this.categoryName,
    required this.categorySlug,
    required this.categoryId,
    required this.unitLabel,
    required this.packaging,
    required this.brand,
    required this.usageArea,
    required this.materialGrade,
    required this.color,
    required this.standard,
    required this.warranty,
    required this.originCountry,
    required this.contentPerUnit,
    required this.contentUnit,
    required this.unitsPerPackage,
    required this.coveragePerUnitM2,
    required this.weightKg,
    required this.lengthCm,
    required this.widthCm,
    required this.heightCm,
    required this.volumeM3,
    required this.fragile,
    required this.requiresUnloading,
    required this.price,
    required this.finalPrice,
    required this.promoPrice,
    required this.originalPublicPrice,
    required this.discountPercent,
    required this.stock,
    required this.minOrderQuantity,
    required this.canAddToCart,
    required this.isNegotiable,
    required this.isOrderable,
    required this.availabilityLabel,
    required this.rating,
    required this.reviewsCount,
    required this.sales,
    required this.imageUrl,
    required this.galleryUrls,
    required this.tags,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    double toDouble(dynamic value) {
      if (value is num) return value.toDouble();
      return double.tryParse(value?.toString() ?? '') ?? 0;
    }

    int toInt(dynamic value) {
      if (value is num) return value.toInt();
      return int.tryParse(value?.toString() ?? '') ?? 0;
    }

    String clean(dynamic value) => cleanOvanieText(value?.toString() ?? '');

    final rawImage = json['main_image_url'] ??
        json['card_image_url'] ??
        json['image'] ??
        _firstImage(json['images']);

    final rawTags = json['tags'];
    final categoryMap =
        json['category'] is Map ? Map<String, dynamic>.from(json['category']) : null;
    final categorySlug = json['category_slug']?.toString() ??
        categoryMap?['slug']?.toString() ??
        '';
    final rawCategoryName = json['category_name']?.toString() ??
        categoryMap?['name']?.toString() ??
        '';

    final rawAttributes = json['product_attributes'];
    final attributes = <String, String>{};
    if (rawAttributes is Map) {
      for (final entry in rawAttributes.entries) {
        final key = clean(entry.key);
        final value = clean(entry.value);
        if (key.isNotEmpty && value.isNotEmpty) attributes[key] = value;
      }
    }

    final rawTechnicalSpecs = json['technical_specs'];
    final technicalSpecs = <String, String>{};
    if (rawTechnicalSpecs is Map) {
      for (final entry in rawTechnicalSpecs.entries) {
        final key = clean(entry.key);
        final value = clean(entry.value);
        if (key.isNotEmpty && value.isNotEmpty) technicalSpecs[key] = value;
      }
    }

    final gallery = <String>[];
    void addMedia(dynamic value) {
      final normalized = AppConfig.normalizeMediaUrl(value?.toString());
      if (normalized.isNotEmpty && !gallery.contains(normalized)) gallery.add(normalized);
    }

    final rawGallery = json['gallery'];
    if (rawGallery is List) {
      for (final item in rawGallery) addMedia(item);
    }
    final images = json['images'];
    if (images is List) {
      for (final item in images) {
        if (item is Map) {
          addMedia(item['url'] ?? item['card_url'] ?? item['thumb_url']);
        } else {
          addMedia(item);
        }
      }
    }
    addMedia(rawImage);

    final price = toDouble(json['price']);
    final finalPrice = toDouble(json['final_price'] ?? json['price']);
    final promoPrice = json['promo_price'] == null
        ? null
        : toDouble(json['promo_price']);
    final originalPublicPrice = toDouble(
      json['original_public_price'] ?? json['base_price'] ?? json['price'],
    );

    final apiDiscount = toInt(json['discount_percent']);
    final computedDiscount = price > 0 &&
            promoPrice != null &&
            promoPrice > 0 &&
            promoPrice < price
        ? (((price - promoPrice) / price) * 100).round()
        : 0;

    return ProductModel(
      id: toInt(json['id']),
      slug: json['slug']?.toString() ?? '',
      name: cleanOvanieText(
        json['name']?.toString().trim().isNotEmpty == true
            ? json['name'].toString()
            : 'Produit OVANIE',
        fallbackSlug: json['slug']?.toString() ?? '',
      ),
      shortDescription: clean(json['short_description']),
      description: clean(json['description']),
      technicalDetails: clean(json['technical_details']),
      attributes: Map<String, String>.unmodifiable(attributes),
      technicalSpecs: Map<String, String>.unmodifiable(technicalSpecs),
      reference: clean(json['reference'] ?? json['sku']),
      productStateLabel: clean(json['product_state_label'] ?? 'Neuf'),
      supplyDelay: clean(json['supply_delay']),
      returnPolicy: clean(json['return_policy']),
      availabilityStatus: clean(json['availability_status']),
      categoryName:
          canonicalCategoryName(slug: categorySlug, rawName: rawCategoryName),
      categorySlug: categorySlug,
      categoryId: toInt(json['category_id'] ?? categoryMap?['id']),
      unitLabel: clean(json['unit_label'] ??
          json['display_unit'] ??
          json['unit'] ??
          'unité'),
      packaging: clean(json['packaging']),
      brand: clean(json['brand']),
      usageArea: clean(json['usage_area']),
      materialGrade: clean(json['material_grade']),
      color: clean(json['color']),
      standard: clean(json['standard']),
      warranty: clean(json['warranty']),
      originCountry: clean(json['origin_country']),
      contentPerUnit: toDouble(json['content_per_unit']),
      contentUnit: clean(json['content_unit']),
      unitsPerPackage: toInt(json['units_per_package']),
      coveragePerUnitM2: toDouble(json['coverage_per_unit_m2']),
      weightKg: toDouble(json['weight_kg']),
      lengthCm: toDouble(json['length_cm']),
      widthCm: toDouble(json['width_cm']),
      heightCm: toDouble(json['height_cm']),
      volumeM3: toDouble(json['volume_m3']),
      fragile: json['fragile'] == true || json['fragile'] == 1,
      requiresUnloading:
          json['requires_unloading'] == true || json['requires_unloading'] == 1,
      price: price,
      finalPrice: finalPrice,
      promoPrice: promoPrice,
      originalPublicPrice: originalPublicPrice > 0 ? originalPublicPrice : price,
      discountPercent: apiDiscount > 0 ? apiDiscount : computedDiscount,
      stock: toInt(json['stock']),
      minOrderQuantity: toInt(json['min_order_quantity']) <= 0
          ? 1
          : toInt(json['min_order_quantity']),
      canAddToCart: json['can_add_to_cart'] == true,
      isNegotiable: json['is_negotiable'] == true,
      isOrderable: json['is_orderable'] == true,
      availabilityLabel: clean(json['availability_label'] ??
          (toInt(json['stock']) > 0 ? 'En stock' : 'Indisponible')),
      rating: json['rating'] == null && json['average_rating'] == null
          ? null
          : toDouble(json['rating'] ?? json['average_rating']),
      reviewsCount: toInt(json['reviews_count']),
      sales: toInt(json['sales']),
      imageUrl: AppConfig.normalizeMediaUrl(rawImage?.toString()),
      galleryUrls: List<String>.unmodifiable(gallery),
      tags: rawTags is List
          ? rawTags.map((e) => clean(e)).where((e) => e.isNotEmpty).toList()
          : const [],
    );
  }

  static String _firstImage(dynamic images) {
    if (images is! List || images.isEmpty) return '';
    final first = images.first;
    if (first is Map) {
      return first['url']?.toString() ?? first['card_url']?.toString() ?? '';
    }
    return first?.toString() ?? '';
  }

  bool get hasDiscount => discountPercent > 0 ||
      (promoPrice != null && promoPrice! > 0 && promoPrice! < price);

  /// Prix affiché sur l'accueil mobile. Pour une promotion active, Laravel
  /// fournit le même `promo_price` que la page Web.
  double get homeDisplayPrice {
    if (promoPrice != null && promoPrice! > 0 && promoPrice! < price) {
      return promoPrice!;
    }
    return finalPrice > 0 ? finalPrice : price;
  }

  /// Ancien prix barré utilisé pour calculer/afficher une vraie réduction.
  double get homeOriginalPrice {
    if (discountPercent <= 0) return 0;
    if (price > 0) return price;
    return originalPublicPrice;
  }

  /// Sous-titre compact de la carte Home (poids, dimensions ou unité).
  String get homeSubtitle {
    if (weightKg > 0) return '${compactNumber(weightKg)} kg';
    if (dimensionsLabel.isNotEmpty) return dimensionsLabel;
    if (packaging.trim().isNotEmpty) return packaging.trim();
    if (unitLabel.trim().isNotEmpty) return unitLabel.trim();
    return 'Produit OVANIE';
  }

  bool get hasDimensions => lengthCm > 0 || widthCm > 0 || heightCm > 0;

  String get dimensionsLabel {
    final values = <String>[];
    if (lengthCm > 0) values.add('${compactNumber(lengthCm)} cm');
    if (widthCm > 0) values.add('${compactNumber(widthCm)} cm');
    if (heightCm > 0) values.add('${compactNumber(heightCm)} cm');
    return values.join(' × ');
  }

  static String compactNumber(double value) {
    if (value == value.roundToDouble()) return value.round().toString();
    return value.toStringAsFixed(1);
  }

  Map<String, dynamic> toCacheJson() => <String, dynamic>{
        'id': id,
        'slug': slug,
        'name': name,
        'short_description': shortDescription,
        'description': description,
        'technical_details': technicalDetails,
        'product_attributes': attributes,
        'technical_specs': technicalSpecs,
        'reference': reference,
        'product_state_label': productStateLabel,
        'supply_delay': supplyDelay,
        'return_policy': returnPolicy,
        'availability_status': availabilityStatus,
        'category_name': categoryName,
        'category_slug': categorySlug,
        'category_id': categoryId,
        'unit_label': unitLabel,
        'packaging': packaging,
        'brand': brand,
        'usage_area': usageArea,
        'material_grade': materialGrade,
        'color': color,
        'standard': standard,
        'warranty': warranty,
        'origin_country': originCountry,
        'content_per_unit': contentPerUnit,
        'content_unit': contentUnit,
        'units_per_package': unitsPerPackage,
        'coverage_per_unit_m2': coveragePerUnitM2,
        'weight_kg': weightKg,
        'length_cm': lengthCm,
        'width_cm': widthCm,
        'height_cm': heightCm,
        'volume_m3': volumeM3,
        'fragile': fragile,
        'requires_unloading': requiresUnloading,
        'price': price,
        'final_price': finalPrice,
        'promo_price': promoPrice,
        'original_public_price': originalPublicPrice,
        'discount_percent': discountPercent,
        'stock': stock,
        'min_order_quantity': minOrderQuantity,
        'can_add_to_cart': canAddToCart,
        'is_negotiable': isNegotiable,
        'is_orderable': isOrderable,
        'availability_label': availabilityLabel,
        'rating': rating,
        'reviews_count': reviewsCount,
        'sales': sales,
        'main_image_url': imageUrl,
        'gallery': galleryUrls,
        'tags': tags,
      };
}
