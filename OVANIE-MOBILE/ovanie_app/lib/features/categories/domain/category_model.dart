import '../../../core/config/app_config.dart';
import '../../../core/utils/text_cleaner.dart';

class CategoryModel {
  final int id;
  final int? parentId;
  final String name;
  final String slug;
  final String icon;
  final String imageUrl;
  final List<CategoryModel> children;

  const CategoryModel({
    required this.id,
    required this.parentId,
    required this.name,
    required this.slug,
    required this.icon,
    required this.imageUrl,
    required this.children,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    int toInt(dynamic value) {
      if (value is num) return value.toInt();
      return int.tryParse(value?.toString() ?? '') ?? 0;
    }

    final rawChildren = json['children'];
    final slug = json['slug']?.toString() ?? '';
    final rawName = json['public_name']?.toString() ??
        json['name']?.toString() ??
        'Catégorie';

    final parentValue = json['parent_id'];
    final parsedParentId = parentValue == null ? null : toInt(parentValue);

    return CategoryModel(
      id: toInt(json['id']),
      parentId: parsedParentId == 0 ? null : parsedParentId,
      name: canonicalCategoryName(slug: slug, rawName: rawName),
      slug: slug,
      icon: json['icon']?.toString() ?? '',
      imageUrl: AppConfig.normalizeMediaUrl(
        json['image_url']?.toString() ?? json['image']?.toString() ?? '',
      ),
      children: rawChildren is List
          ? rawChildren
              .whereType<Map>()
              .map((item) => CategoryModel.fromJson(Map<String, dynamic>.from(item)))
              .toList()
          : const [],
    );
  }

  CategoryModel copyWith({
    int? id,
    int? parentId,
    bool clearParentId = false,
    String? name,
    String? slug,
    String? icon,
    String? imageUrl,
    List<CategoryModel>? children,
  }) {
    return CategoryModel(
      id: id ?? this.id,
      parentId: clearParentId ? null : (parentId ?? this.parentId),
      name: name ?? this.name,
      slug: slug ?? this.slug,
      icon: icon ?? this.icon,
      imageUrl: imageUrl ?? this.imageUrl,
      children: children ?? this.children,
    );
  }
}
