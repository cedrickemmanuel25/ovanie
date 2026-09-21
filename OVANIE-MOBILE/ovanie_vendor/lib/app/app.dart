import 'dart:async';

import 'package:flutter/material.dart';

import '../core/platform/app_deep_link.dart';
import '../features/auth/reset_password_screen.dart';
import '../features/splash/splash_screen.dart';
import 'theme.dart';

class OvanieVendorApp extends StatefulWidget {
  const OvanieVendorApp({super.key});

  @override
  State<OvanieVendorApp> createState() => _OvanieVendorAppState();
}

class _OvanieVendorAppState extends State<OvanieVendorApp> {
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();
  StreamSubscription<Uri>? _deepLinkSubscription;
  bool _handlingLink = false;

  @override
  void initState() {
    super.initState();
    _bootDeepLinks();
  }

  Future<void> _bootDeepLinks() async {
    _deepLinkSubscription =
        VendorDeepLinkBridge.instance.links.listen(_handleDeepLink);
    final initial = await VendorDeepLinkBridge.instance.start();
    if (initial == null) return;

    // Le Splash restaure la session avant la navigation. On attend qu'un
    // Navigator stable soit monté afin que l'écran de récupération ne soit
    // pas remplacé immédiatement par le Splash.
    for (var attempt = 0; attempt < 30; attempt++) {
      if (_navigatorKey.currentState != null) break;
      await Future<void>.delayed(const Duration(milliseconds: 120));
    }
    await Future<void>.delayed(const Duration(milliseconds: 700));
    await _handleDeepLink(initial);
  }

  Future<void> _handleDeepLink(Uri uri) async {
    if (_handlingLink || uri.scheme.toLowerCase() != 'ovanie-vendeur') return;
    if (uri.host.toLowerCase() != 'auth' || uri.path != '/reset-password') return;

    final token = uri.queryParameters['token'] ?? '';
    final email = uri.queryParameters['email'] ?? '';
    if (token.trim().isEmpty || email.trim().isEmpty) return;

    final navigator = _navigatorKey.currentState;
    if (navigator == null) return;

    _handlingLink = true;
    try {
      await navigator.push<void>(
        MaterialPageRoute<void>(
          builder: (_) => ResetPasswordScreen(token: token, email: email),
        ),
      );
    } finally {
      _handlingLink = false;
    }
  }

  @override
  void dispose() {
    _deepLinkSubscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: _navigatorKey,
      debugShowCheckedModeBanner: false,
      title: 'OVANIE Vendeur',
      theme: OvanieTheme.light,
      builder: (context, child) {
        final clamped = MediaQuery.textScalerOf(context)
            .clamp(minScaleFactor: 0.9, maxScaleFactor: 1.15);
        return MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: clamped),
          child: child!,
        );
      },
      home: const SplashScreen(),
    );
  }
}
