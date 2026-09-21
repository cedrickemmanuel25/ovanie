import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../auth/domain/session_store.dart';
import '../../products/domain/product_model.dart';
import '../data/favorites_repository.dart';

/// Miroir mobile de la table `favorites` Laravel.
///
/// Le Web et l'application utilisent la même table. Flutter n'entretient pas
/// une seconde liste de favoris indépendante : après authentification, Laravel
/// est toujours rechargé et chaque action est synchronisée vers le serveur.
class FavoritesStore extends ChangeNotifier {
  FavoritesStore._();

  static final FavoritesStore instance = FavoritesStore._();
  static const FavoritesRepository _repository = FavoritesRepository();

  final Map<int, ProductModel> _items = <int, ProductModel>{};
  bool _loading = false;
  Object? _lastError;

  List<ProductModel> get items => List<ProductModel>.unmodifiable(_items.values);
  int get count => _items.length;
  bool get loading => _loading;
  Object? get lastError => _lastError;

  bool contains(ProductModel product) => _items.containsKey(product.id);
  bool containsId(int productId) => _items.containsKey(productId);

  Future<void> refreshFromServer() async {
    if (!SessionStore.instance.isAuthenticated) {
      clearLocalOnly();
      return;
    }

    _loading = true;
    _lastError = null;
    notifyListeners();
    try {
      final products = await _repository.fetchAll();
      _items
        ..clear()
        ..addEntries(products.map((product) => MapEntry(product.id, product)));
    } catch (error) {
      _lastError = error;
      rethrow;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  /// Optimiste côté UI, puis synchronisé vers Laravel.
  void toggle(ProductModel product) {
    if (!SessionStore.instance.isAuthenticated || product.id <= 0) return;

    final wasFavorite = _items.containsKey(product.id);
    if (wasFavorite) {
      _items.remove(product.id);
    } else {
      _items[product.id] = product;
    }
    notifyListeners();

    unawaited(_persistToggle(product, wasFavorite));
  }

  Future<void> _persistToggle(ProductModel product, bool wasFavorite) async {
    try {
      if (wasFavorite) {
        await _repository.remove(product.id);
      } else {
        await _repository.add(product.id);
      }
      _lastError = null;
    } catch (error) {
      // Restaurer l'état si le serveur a refusé la modification.
      if (wasFavorite) {
        _items[product.id] = product;
      } else {
        _items.remove(product.id);
      }
      _lastError = error;
      notifyListeners();
    }
  }

  Future<void> remove(ProductModel product) async {
    if (!SessionStore.instance.isAuthenticated) return;
    final previous = _items.remove(product.id);
    notifyListeners();
    try {
      await _repository.remove(product.id);
    } catch (error) {
      if (previous != null) _items[product.id] = previous;
      _lastError = error;
      notifyListeners();
      rethrow;
    }
  }

  Future<void> clear() async {
    if (!SessionStore.instance.isAuthenticated) {
      clearLocalOnly();
      return;
    }

    final previous = Map<int, ProductModel>.from(_items);
    _items.clear();
    notifyListeners();
    try {
      await _repository.clear();
    } catch (error) {
      _items
        ..clear()
        ..addAll(previous);
      _lastError = error;
      notifyListeners();
      rethrow;
    }
  }

  void clearLocalOnly() {
    if (_items.isEmpty && _lastError == null && !_loading) return;
    _items.clear();
    _loading = false;
    _lastError = null;
    notifyListeners();
  }
}
