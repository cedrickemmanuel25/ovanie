import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../auth/domain/session_store.dart';
import '../../products/domain/product_model.dart';
import '../data/recently_viewed_repository.dart';

/// Historique local pour les visiteurs et miroir synchronisé pour les comptes.
///
/// Lorsqu'un client est connecté, Laravel reste la source partagée Web/mobile.
/// Le miroir local permet uniquement de garder l'interface réactive hors ligne.
class RecentlyViewedStore extends ChangeNotifier {
  RecentlyViewedStore._();

  static final RecentlyViewedStore instance = RecentlyViewedStore._();
  static const int _maxItems = 30;
  static const RecentlyViewedRepository _repository = RecentlyViewedRepository();

  final List<ProductModel> _items = [];
  bool _syncing = false;

  List<ProductModel> get items => List<ProductModel>.unmodifiable(_items);
  bool get syncing => _syncing;

  void record(ProductModel product) {
    _putFirst(product);
    notifyListeners();

    if (SessionStore.instance.isAuthenticated) {
      unawaited(_repository.record(product.id).catchError((_) {}));
    }
  }

  Future<void> syncFromServer() async {
    if (!SessionStore.instance.isAuthenticated || _syncing) return;
    _syncing = true;
    notifyListeners();
    try {
      final serverItems = await _repository.list();
      _items
        ..clear()
        ..addAll(serverItems.take(_maxItems));
    } finally {
      _syncing = false;
      notifyListeners();
    }
  }

  Future<void> clear() async {
    final hadItems = _items.isNotEmpty;
    _items.clear();
    if (hadItems) notifyListeners();

    if (SessionStore.instance.isAuthenticated) {
      await _repository.clear();
    }
  }

  void _putFirst(ProductModel product) {
    _items.removeWhere((item) => item.id == product.id);
    _items.insert(0, product);
    if (_items.length > _maxItems) {
      _items.removeRange(_maxItems, _items.length);
    }
  }
}
