import 'dart:async';

import 'package:flutter/material.dart';

import '../core/network/api_runtime_state.dart';
import '../core/platform/app_deep_link.dart';
import '../core/push/push_notification_service.dart';
import '../features/auth/domain/session_store.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/reset_password_screen.dart';
import '../features/auth/presentation/session_expired_screen.dart';
import '../features/account/presentation/notifications_screen.dart';
import '../features/cart/data/cart_api_repository.dart';
import '../features/checkout/presentation/order_confirmation_screen.dart';
import '../features/orders/presentation/order_detail_screen.dart';
import '../features/returns/presentation/returns_screen.dart';
import '../features/support/presentation/support_ticket_detail_screen.dart';
import '../features/splash/presentation/splash_screen.dart';
import '../shared/widgets/offline_network_screen.dart';
import 'theme.dart';

class OvanieApp extends StatefulWidget {
  const OvanieApp({super.key});

  @override
  State<OvanieApp> createState() => _OvanieAppState();
}

class _OvanieAppState extends State<OvanieApp> {
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();
  StreamSubscription<Uri>? _deepLinkSubscription;
  StreamSubscription<PushNavigationTarget>? _pushNavigationSubscription;
  Uri? _initialLink;
  bool _handlingLink = false;
  bool _clearingExpiredSession = false;
  bool _openingRecoveryLogin = false;

  @override
  void initState() {
    super.initState();
    ApiRuntimeState.instance.addListener(_handleRuntimeState);
    _bootDeepLinks();
    _pushNavigationSubscription = PushNotificationService.instance.navigation.listen(_handlePushTarget);
  }

  void _handleRuntimeState() {
    if (!ApiRuntimeState.instance.isSessionExpired ||
        _clearingExpiredSession) {
      return;
    }

    _clearingExpiredSession = true;
    unawaited(
      SessionStore.instance.expireFromServer().whenComplete(() {
        _clearingExpiredSession = false;
      }),
    );
  }

  Future<void> _openSessionRecoveryLogin() async {
    if (_openingRecoveryLogin) return;
    final navigator = _navigatorKey.currentState;
    if (navigator == null) return;

    _openingRecoveryLogin = true;
    ApiRuntimeState.instance.resolveSessionExpired();

    try {
      await navigator.push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => LoginScreen(
            initialIdentifier: SessionStore.instance.lastIdentifier,
            sessionRecovery: true,
          ),
        ),
      );
    } finally {
      _openingRecoveryLogin = false;
    }
  }

  Future<void> _bootDeepLinks() async {
    _deepLinkSubscription =
        AppDeepLinkBridge.instance.links.listen(_handleDeepLink);
    _initialLink = await AppDeepLinkBridge.instance.start();

    // Le Splash attend maintenant l'initialisation réelle. On patiente jusqu'à
    // ce qu'un Navigator soit monté et qu'il ait quitté le Splash avant de
    // traiter un retour de paiement reçu au démarrage à froid.
    if (_initialLink != null) {
      for (var attempt = 0; attempt < 80; attempt++) {
        await Future<void>.delayed(const Duration(milliseconds: 150));
        if (ApiRuntimeState.instance.isBootCompleted &&
            _navigatorKey.currentState != null) {
          break;
        }
      }
      final link = _initialLink;
      _initialLink = null;
      if (link != null) await _handleDeepLink(link);
    }
  }

  Future<void> _handleDeepLink(Uri uri) async {
    if (_handlingLink || uri.scheme.toLowerCase() != 'ovanie') return;

    final host = uri.host.toLowerCase();
    if (host == 'auth' && uri.path == '/reset-password') {
      final token = uri.queryParameters['token'] ?? '';
      final email = uri.queryParameters['email'] ?? '';
      if (token.isEmpty || email.isEmpty) return;
      final navigator = _navigatorKey.currentState;
      if (navigator == null) return;
      _handlingLink = true;
      try {
        await navigator.push<void>(MaterialPageRoute<void>(builder: (_) => ResetPasswordScreen(token: token, email: email)));
      } finally {
        _handlingLink = false;
      }
      return;
    }

    if (host != 'payment' || uri.path != '/return') return;
    final orderId = int.tryParse(uri.queryParameters['order_id'] ?? '') ?? 0;
    if (orderId <= 0) return;

    _handlingLink = true;
    try {
      if (SessionStore.instance.isAuthenticated) {
        try { await const CartApiRepository().refreshLocalCartFromServer(); } catch (_) {}
      }
      final navigator = _navigatorKey.currentState;
      if (navigator == null) return;
      navigator.pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => OrderConfirmationScreen(
            orderId: orderId,
            initialOrderNumber: uri.queryParameters['order_number'] ?? '',
            returnState: uri.queryParameters['state'] ?? '',
            initialPaymentMethod: 'Paiement en ligne',
          ),
        ),
        (route) => route.isFirst,
      );
    } finally {
      _handlingLink = false;
    }
  }

  Future<void> _handlePushTarget(PushNavigationTarget target) async {
    if (!SessionStore.instance.isAuthenticated) return;
    for (var attempt = 0; attempt < 40 && !ApiRuntimeState.instance.isBootCompleted; attempt++) {
      await Future<void>.delayed(const Duration(milliseconds: 150));
    }
    final navigator = _navigatorKey.currentState;
    if (navigator == null) return;
    if (target.orderId != null) {
      navigator.push(MaterialPageRoute<void>(builder: (_) => OrderDetailScreen(orderId: target.orderId!)));
      return;
    }
    if (target.ticketId != null) {
      navigator.push(MaterialPageRoute<void>(builder: (_) => SupportTicketDetailScreen(ticketId: target.ticketId!)));
      return;
    }
    if (target.returnId != null || target.category == 'returns') {
      navigator.push(MaterialPageRoute<void>(builder: (_) => const ReturnsScreen()));
      return;
    }
    navigator.push(MaterialPageRoute<void>(builder: (_) => const NotificationsScreen()));
  }

  @override
  void dispose() {
    ApiRuntimeState.instance.removeListener(_handleRuntimeState);
    _deepLinkSubscription?.cancel();
    _pushNavigationSubscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: _navigatorKey,
      title: 'OVANIE',
      debugShowCheckedModeBanner: false,
      theme: OvanieTheme.light,
      home: const SplashScreen(),
      builder: (context, child) {
        return AnimatedBuilder(
          animation: ApiRuntimeState.instance,
          builder: (context, _) {
            final runtime = ApiRuntimeState.instance;
            return Stack(
              children: [
                Positioned.fill(child: child ?? const SizedBox.shrink()),
                if (runtime.isOffline)
                  const Positioned.fill(child: OfflineNetworkScreen())
                else if (runtime.isSessionExpired)
                  Positioned.fill(
                    child: SessionExpiredScreen(
                      onReconnect: _openSessionRecoveryLogin,
                    ),
                  ),
              ],
            );
          },
        );
      },
    );
  }
}
