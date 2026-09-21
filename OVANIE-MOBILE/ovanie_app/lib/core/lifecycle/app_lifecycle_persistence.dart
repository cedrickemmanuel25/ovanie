import 'dart:async';

import 'package:flutter/widgets.dart';

import '../../features/auth/domain/session_store.dart';
import '../../features/cart/domain/cart_store.dart';
import '../../features/cart/data/cart_api_repository.dart';

/// Force la persistance du panier et de la session lorsque l'application
/// quitte l'avant-plan. Cela couvre le bouton Home, le multitâche et la
/// fermeture de l'application depuis Android.
class AppLifecyclePersistence with WidgetsBindingObserver {
  AppLifecyclePersistence._();

  bool _refreshingCart = false;

  static final AppLifecyclePersistence instance = AppLifecyclePersistence._();

  void start() {
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        SessionStore.instance.isAuthenticated) {
      unawaited(_refreshAuthenticatedCart());
      return;
    }

    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.detached) {
      unawaited(CartStore.instance.flush());
      unawaited(SessionStore.instance.flush());
      if (SessionStore.instance.isAuthenticated) {
        unawaited(CartStore.instance.flushPendingServerMutations());
      }
    }
  }

  Future<void> _refreshAuthenticatedCart() async {
    if (_refreshingCart) return;
    _refreshingCart = true;
    try {
      await const CartApiRepository().refreshLocalCartFromServer();
    } catch (_) {
      // Le miroir local reste utilisable hors connexion.
    } finally {
      _refreshingCart = false;
    }
  }
}
