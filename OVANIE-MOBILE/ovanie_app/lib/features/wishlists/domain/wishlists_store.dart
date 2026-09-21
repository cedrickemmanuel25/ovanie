import 'package:flutter/foundation.dart';

import '../../auth/domain/session_store.dart';
import '../../products/domain/product_model.dart';
import '../data/wishlists_repository.dart';
import 'wishlist_model.dart';

class WishlistsStore extends ChangeNotifier {
  WishlistsStore._();

  static final WishlistsStore instance = WishlistsStore._();
  static const WishlistsRepository _repository = WishlistsRepository();

  List<WishlistModel> _items = const <WishlistModel>[];
  bool _loading = false;
  Object? _lastError;

  List<WishlistModel> get items => List<WishlistModel>.unmodifiable(_items);
  bool get loading => _loading;
  Object? get lastError => _lastError;

  Future<void> refresh() async {
    if (!SessionStore.instance.isAuthenticated) {
      _items = const <WishlistModel>[];
      _lastError = null;
      notifyListeners();
      return;
    }

    _loading = true;
    _lastError = null;
    notifyListeners();
    try {
      _items = await _repository.fetchAll();
    } catch (error) {
      _lastError = error;
      rethrow;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<WishlistModel> create(String name) async {
    final created = await _repository.create(name);
    _items = <WishlistModel>[created, ..._items];
    notifyListeners();
    return created;
  }

  Future<void> delete(WishlistModel wishlist) async {
    final previous = _items;
    _items = _items.where((item) => item.id != wishlist.id).toList(growable: false);
    notifyListeners();
    try {
      await _repository.delete(wishlist.id);
    } catch (_) {
      _items = previous;
      notifyListeners();
      rethrow;
    }
  }

  Future<void> setAlerts(WishlistModel wishlist, bool enabled) async {
    final updated = await _repository.update(
      wishlist.id,
      alertsEnabled: enabled,
    );
    _replace(updated);
  }

  Future<void> addProduct(WishlistModel wishlist, ProductModel product) async {
    await _repository.addProduct(wishlist.id, product);
    await refresh();
  }

  Future<void> removeProduct(WishlistModel wishlist, ProductModel product) async {
    await _repository.removeProduct(wishlist.id, product.id);
    await refresh();
  }

  void _replace(WishlistModel updated) {
    _items = _items
        .map((item) => item.id == updated.id ? updated : item)
        .toList(growable: false);
    notifyListeners();
  }
}
