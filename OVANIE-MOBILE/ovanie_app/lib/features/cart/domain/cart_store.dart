import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../../../core/storage/device_storage.dart';
import '../../auth/domain/session_store.dart';
import '../../products/domain/product_model.dart';
import '../data/cart_server_sync.dart';

class CartLine {
  final ProductModel product;
  final int quantity;
  final double unitPrice;

  CartLine({
    required this.product,
    required this.quantity,
    double? unitPrice,
  }) : unitPrice = unitPrice ?? product.finalPrice;

  double get subtotal => unitPrice * quantity;

  CartLine copyWith({int? quantity}) {
    return CartLine(
      product: product,
      quantity: quantity ?? this.quantity,
      unitPrice: unitPrice,
    );
  }
}

class CartAddResult {
  final bool success;
  final String message;

  const CartAddResult({required this.success, required this.message});
}

/// Panier OVANIE persistant et synchronisé avec le panier Laravel.
///
/// Règles :
/// - non connecté : le panier reste uniquement sur le téléphone ;
/// - connecté : chaque ajout, quantité, suppression et vidage est envoyé au
///   même panier Laravel utilisé par le web ;
/// - si le réseau coupe pendant une suppression, l'intention est conservée
///   sur le téléphone et réessayée avant la prochaine fusion serveur. Ainsi un
///   article supprimé ne peut plus réapparaître au prochain démarrage.
class CartStore extends ChangeNotifier {
  CartStore._();

  static final CartStore instance = CartStore._();

  static const String _storageKey = 'ovanie_guest_cart_v1';
  static const String _syncStorageKey = 'ovanie_cart_pending_sync_v1';

  final Map<int, CartLine> _lines = <int, CartLine>{};

  /// userId -> (productId -> quantité cible). Une quantité 0 = suppression.
  final Map<int, Map<int, int>> _pendingByUser = <int, Map<int, int>>{};
  final Set<int> _clearPendingUsers = <int>{};

  bool _restored = false;
  int _localMutationRevision = 0;
  Future<void> _storageChain = Future<void>.value();
  Future<void> _serverChain = Future<void>.value();

  List<CartLine> get lines => List<CartLine>.unmodifiable(_lines.values);
  int get localMutationRevision => _localMutationRevision;

  int get itemsCount =>
      _lines.values.fold<int>(0, (sum, line) => sum + line.quantity);

  double get subtotal =>
      _lines.values.fold<double>(0, (sum, line) => sum + line.subtotal);

  bool contains(int productId) => _lines.containsKey(productId);

  int quantityFor(int productId) => _lines[productId]?.quantity ?? 0;

  Future<void> restore() async {
    if (_restored) return;
    _restored = true;

    await _restoreCart();
    await _restorePendingSync();
  }

  Future<void> _restoreCart() async {
    final raw = await DeviceStorage.instance.readString(_storageKey);
    if (raw == null || raw.trim().isEmpty) return;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return;

      final restored = <int, CartLine>{};
      for (final item in decoded) {
        if (item is! Map) continue;

        final map = Map<String, dynamic>.from(item);
        final productRaw = map['product'];
        final quantity = int.tryParse('${map['quantity'] ?? ''}') ?? 0;
        final unitPrice = map['unit_price'] is num
            ? (map['unit_price'] as num).toDouble()
            : double.tryParse('${map['unit_price'] ?? ''}');
        if (productRaw is! Map || quantity <= 0) continue;

        final product = ProductModel.fromJson(
          Map<String, dynamic>.from(productRaw),
        );
        if (product.id <= 0) continue;

        restored[product.id] = CartLine(
          product: product,
          quantity: quantity,
          unitPrice: unitPrice,
        );
      }

      if (restored.isNotEmpty) {
        _lines
          ..clear()
          ..addAll(restored);
        notifyListeners();
      }
    } catch (_) {
      // Une ancienne sauvegarde invalide ne doit pas bloquer l'application.
    }
  }

  Future<void> _restorePendingSync() async {
    final raw = await DeviceStorage.instance.readString(_syncStorageKey);
    if (raw == null || raw.trim().isEmpty) return;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return;
      final root = Map<String, dynamic>.from(decoded);

      final clearUsers = root['clear_users'];
      if (clearUsers is List) {
        for (final value in clearUsers) {
          final id = int.tryParse('$value') ?? 0;
          if (id > 0) _clearPendingUsers.add(id);
        }
      }

      final quantities = root['quantities'];
      if (quantities is Map) {
        for (final userEntry in quantities.entries) {
          final userId = int.tryParse('${userEntry.key}') ?? 0;
          if (userId <= 0 || userEntry.value is! Map) continue;

          final targets = <int, int>{};
          for (final productEntry in (userEntry.value as Map).entries) {
            final productId = int.tryParse('${productEntry.key}') ?? 0;
            final quantity = int.tryParse('${productEntry.value}') ?? -1;
            if (productId > 0 && quantity >= 0) {
              targets[productId] = quantity;
            }
          }
          if (targets.isNotEmpty) _pendingByUser[userId] = targets;
        }
      }
    } catch (_) {
      // Les mutations locales restent prioritaires ; ignorer un ancien format.
    }
  }

  CartAddResult add(ProductModel product) {
    if (!product.canAddToCart || product.stock <= 0) {
      return CartAddResult(
        success: false,
        message: product.isOrderable
            ? 'Ce produit est disponible sur commande.'
            : 'Ce produit n’est pas disponible à l’achat pour le moment.',
      );
    }

    final minimum = product.minOrderQuantity <= 0 ? 1 : product.minOrderQuantity;
    final current = _lines[product.id]?.quantity ?? 0;
    final target = current == 0 ? minimum : current + minimum;

    if (target > product.stock) {
      return const CartAddResult(
        success: false,
        message: 'Stock insuffisant pour ajouter davantage de ce produit.',
      );
    }

    _lines[product.id] = CartLine(product: product, quantity: target);
    notifyListeners();
    _afterLocalQuantityChanged(product.id, target);

    return const CartAddResult(
      success: true,
      message: 'Produit ajouté au panier.',
    );
  }

  /// Reflète localement une ligne déjà créée côté serveur au prix négocié
  /// (voir NegotiationRepository.addNegotiatedToCart). Contrairement à
  /// addQuantity(), n'appelle jamais _afterLocalQuantityChanged : cette
  /// méthode programmerait une synchronisation via /api/cart, qui
  /// recalculerait le prix normal du produit et écraserait le prix
  /// négocié déjà appliqué côté serveur.
  void applyNegotiatedLine(ProductModel product, int quantity, double negotiatedPrice) {
    final current = _lines[product.id]?.quantity ?? 0;
    _lines[product.id] = CartLine(
      product: product,
      quantity: current + quantity,
      unitPrice: negotiatedPrice,
    );
    _localMutationRevision++;
    notifyListeners();
    _persistSoon();
  }

  CartAddResult addQuantity(ProductModel product, int quantity) {
    if (!product.canAddToCart || product.stock <= 0) {
      return CartAddResult(
        success: false,
        message: product.isOrderable
            ? 'Ce produit est disponible sur commande.'
            : 'Ce produit n’est pas disponible à l’achat pour le moment.',
      );
    }

    final minimum = product.minOrderQuantity <= 0 ? 1 : product.minOrderQuantity;
    final requested = quantity < minimum ? minimum : quantity;
    final current = _lines[product.id]?.quantity ?? 0;
    final target = current + requested;

    if (target > product.stock) {
      return const CartAddResult(
        success: false,
        message: 'Stock insuffisant pour cette quantité.',
      );
    }

    _lines[product.id] = CartLine(product: product, quantity: target);
    notifyListeners();
    _afterLocalQuantityChanged(product.id, target);

    return const CartAddResult(
      success: true,
      message: 'Produit ajouté au panier.',
    );
  }

  void increment(int productId) {
    final line = _lines[productId];
    if (line == null) return;

    final step = line.product.minOrderQuantity <= 0
        ? 1
        : line.product.minOrderQuantity;
    final target = line.quantity + step;
    if (target > line.product.stock) return;

    _lines[productId] = line.copyWith(quantity: target);
    notifyListeners();
    _afterLocalQuantityChanged(productId, target);
  }

  void decrement(int productId) {
    final line = _lines[productId];
    if (line == null) return;

    final step = line.product.minOrderQuantity <= 0
        ? 1
        : line.product.minOrderQuantity;
    final target = line.quantity - step;

    if (target < step) {
      remove(productId);
      return;
    }

    _lines[productId] = line.copyWith(quantity: target);
    notifyListeners();
    _afterLocalQuantityChanged(productId, target);
  }

  void remove(int productId) {
    if (_lines.remove(productId) == null) return;
    notifyListeners();
    _afterLocalQuantityChanged(productId, 0);
  }


  /// Suppression explicite depuis l'écran panier : l'état local est écrit sur
  /// disque puis Laravel est synchronisé avant de rendre la main lorsque le
  /// réseau est disponible. En cas de panne, la suppression reste mémorisée
  /// et sera réessayée plus tard.
  Future<bool> removeAndSync(int productId) async {
    remove(productId);
    await flush();
    if (_authenticatedUserId == null) return true;
    try {
      await flushPendingServerMutations();
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> clearAndSync() async {
    clear();
    await flush();
    if (_authenticatedUserId == null) return true;
    try {
      await flushPendingServerMutations();
      return true;
    } catch (_) {
      return false;
    }
  }

  /// Remplace le miroir local par un panier hydraté depuis Laravel.
  /// Cette opération n'est pas une intention utilisateur et ne crée donc pas
  /// de nouvelle mutation serveur.
  void replaceAll(Iterable<CartLine> lines) {
    _lines
      ..clear()
      ..addEntries(lines.map((line) => MapEntry(line.product.id, line)));
    notifyListeners();
    _persistSoon();
  }

  /// Efface uniquement le miroir local du panier sans supprimer le panier
  /// Laravel. Utilisé à la déconnexion pour éviter qu'un autre utilisateur du
  /// même téléphone voie le panier du compte précédent.
  Future<void> clearLocalMirrorOnly() async {
    final userId = _authenticatedUserId;

    if (_lines.isNotEmpty) {
      _lines.clear();
      notifyListeners();
    }

    if (userId != null) {
      _pendingByUser.remove(userId);
      _clearPendingUsers.remove(userId);
    }

    await _persistCurrentAndSyncState();
  }

  void clear() {
    if (_lines.isNotEmpty) {
      _lines.clear();
      _localMutationRevision++;
      notifyListeners();
    }

    final userId = _authenticatedUserId;
    if (userId != null) {
      _clearPendingUsers.add(userId);
      // Toutes les anciennes quantités deviennent obsolètes. Les nouveaux
      // ajouts effectués après ce clear seront enregistrés ensuite.
      _pendingByUser[userId] = <int, int>{};
      _scheduleServerSync();
    }
    _persistSoon();
  }

  int? get _authenticatedUserId {
    final session = SessionStore.instance;
    if (!session.isAuthenticated) return null;
    final id = session.userId;
    return id != null && id > 0 ? id : null;
  }

  void _afterLocalQuantityChanged(int productId, int target) {
    _localMutationRevision++;
    final userId = _authenticatedUserId;
    if (userId != null) {
      (_pendingByUser[userId] ??= <int, int>{})[productId] = target;
      _scheduleServerSync();
    }
    _persistSoon();
  }

  void _scheduleServerSync() {
    _serverChain = _serverChain
        .catchError((_) {})
        .then((_) async {
          try {
            await _flushPendingServerMutationsDirect();
          } catch (_) {
            // Garder les mutations en mémoire/disque. Elles seront réessayées
            // au checkout ou au prochain démarrage.
          }
        });
    unawaited(_serverChain);
  }

  /// Applique d'abord les suppressions/quantités restées en attente puis
  /// retourne. CartApiRepository appelle cette méthode AVANT toute fusion afin
  /// qu'un produit supprimé ne soit jamais réimporté depuis le web.
  Future<void> flushPendingServerMutations() async {
    try {
      await _serverChain;
    } catch (_) {
      // Une tentative précédente peut avoir échoué ; réessayer maintenant.
    }
    await _flushPendingServerMutationsDirect();
  }

  Future<void> _flushPendingServerMutationsDirect() async {
    final userId = _authenticatedUserId;
    if (userId == null) return;

    const remote = CartServerSync();

    if (_clearPendingUsers.contains(userId)) {
      await remote.clear();
      _clearPendingUsers.remove(userId);
      await _persistCurrentAndSyncState();
    }

    final targets = _pendingByUser[userId];
    if (targets == null || targets.isEmpty) return;

    final snapshot = Map<int, int>.from(targets);
    for (final entry in snapshot.entries) {
      await remote.setProductQuantity(entry.key, entry.value);

      // Ne retirer que si l'utilisateur n'a pas modifié à nouveau la quantité
      // pendant la requête réseau.
      final current = _pendingByUser[userId]?[entry.key];
      if (current == entry.value) {
        _pendingByUser[userId]?.remove(entry.key);
      }
      if (_pendingByUser[userId]?.isEmpty ?? false) {
        _pendingByUser.remove(userId);
      }
      await _persistCurrentAndSyncState();
    }
  }

  void _persistSoon() {
    _storageChain = _storageChain
        .catchError((_) {})
        .then((_) => _persistCurrentAndSyncState());
    unawaited(_storageChain);
  }

  /// Force les données locales sur disque avant mise en arrière-plan.
  Future<void> flush() async {
    try {
      await _storageChain;
    } catch (_) {}
    await _persistCurrentAndSyncState();
  }

  Future<void> _persistCurrentAndSyncState() async {
    if (_lines.isEmpty) {
      await DeviceStorage.instance.remove(_storageKey);
    } else {
      final payload = _lines.values
          .map(
            (line) => <String, dynamic>{
              'quantity': line.quantity,
              'unit_price': line.unitPrice,
              'product': _productToJson(line.product),
            },
          )
          .toList(growable: false);

      await DeviceStorage.instance.writeString(
        _storageKey,
        jsonEncode(payload),
      );
    }

    if (_pendingByUser.isEmpty && _clearPendingUsers.isEmpty) {
      await DeviceStorage.instance.remove(_syncStorageKey);
    } else {
      final quantities = <String, dynamic>{};
      for (final userEntry in _pendingByUser.entries) {
        quantities['${userEntry.key}'] = <String, dynamic>{
          for (final productEntry in userEntry.value.entries)
            '${productEntry.key}': productEntry.value,
        };
      }
      await DeviceStorage.instance.writeString(
        _syncStorageKey,
        jsonEncode(<String, dynamic>{
          'clear_users': _clearPendingUsers.toList(growable: false),
          'quantities': quantities,
        }),
      );
    }
  }

  Map<String, dynamic> _productToJson(ProductModel product) =>
      product.toCacheJson();
}
