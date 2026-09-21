import 'package:flutter/foundation.dart';

/// Requête de navigation vers le catalogue principal de l'application.
///
/// V85 : l'accueil et l'onglet « Catégories » ne créent plus deux parcours
/// distincts. Ils pilotent la même instance de [CatalogScreen] via ce store.
class CatalogNavigationRequest {
  final int serial;
  final String? categorySlug;
  final String? categoryName;
  final String? offer;
  final String query;

  const CatalogNavigationRequest({
    required this.serial,
    this.categorySlug,
    this.categoryName,
    this.offer,
    this.query = '',
  });
}

class CatalogNavigationStore extends ChangeNotifier {
  CatalogNavigationStore._();

  static final CatalogNavigationStore instance = CatalogNavigationStore._();

  int _serial = 0;
  CatalogNavigationRequest? _request;

  CatalogNavigationRequest? get request => _request;

  /// Ouvre le catalogue principal avec un contexte optionnel.
  ///
  /// - sans paramètres : catalogue complet ;
  /// - categorySlug : catégorie/sous-catégorie ;
  /// - offer : offre commerciale ;
  /// - query : recherche catalogue.
  void open({
    String? categorySlug,
    String? categoryName,
    String? offer,
    String query = '',
  }) {
    _serial += 1;
    _request = CatalogNavigationRequest(
      serial: _serial,
      categorySlug: _clean(categorySlug),
      categoryName: _clean(categoryName),
      offer: _clean(offer),
      query: query.trim(),
    );
    notifyListeners();
  }

  void openAll() => open();

  void openCategory({required String slug, String? name}) {
    open(categorySlug: slug, categoryName: name);
  }

  void openOffer(String offer) => open(offer: offer);

  static String? _clean(String? value) {
    final normalized = value?.trim();
    return normalized == null || normalized.isEmpty ? null : normalized;
  }
}
